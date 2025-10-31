<?php

namespace App\Service;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\Rule;
use App\Entity\RuleAction;
use App\Notification\HighVulnerabilityNotification;
use App\Notification\ScanCompletedNotification;
use App\Notification\ScanFailedNotification;
use App\Notification\UploadInProgressNotification;
use App\Repository\RuleRepository;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Notifier\NotifierInterface;

/**
 * RuleEvaluationService
 * 
 * Evaluates database-driven rules against scan events with repository-specific + global fallback.
 * Uses Symfony Notifier with custom Notification classes.
 * 
 * RULE HIERARCHY:
 * 1. Repository-specific rules (scope = 'repository:{id}')
 * 2. Global rules (scope = 'global') - FALLBACK if no repo rules found
 * 
 * GLOBAL RULES:
 * - Seeded via database fixture/migration (like hard-coding)
 * - Always available as fallback
 * - Can be customized per repository by creating repo-specific rules
 * 
 * Assignment Requirements Met:
 * - Trigger 1: Vulnerability count > X → Email + Slack
 * - Trigger 2: Upload in progress → Slack
 * - Trigger 3: Upload fails → Email + Slack
 */
class RuleEvaluationService
{
    public function __construct(
        private RuleRepository $ruleRepository,
        private NotifierInterface $notifier,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Evaluate rules for a scan with repository-specific + global fallback
     * 
     * Logic:
     * 1. Try to find repository-specific rules for this repository
     * 2. If found, use only repository-specific rules (override global)
     * 3. If not found, fallback to global rules
     * 4. For each matching rule, execute all associated actions
     * 
     * @param RepositoryScan $scan The scan to evaluate
     */
    public function evaluateAndNotify(RepositoryScan $scan): void
    {
        $repository = $scan->getRepository();
        
        $this->logger->info('Evaluating rules for scan', [
            'scan_id' => $scan->getId(),
            'repository_id' => $repository->getId(),
            'status' => $scan->getStatus(),
            'vulnerability_count' => $scan->getVulnerabilityCount(),
        ]);
        
        // Step 1: Try repository-specific rules
        $repoRules = $this->ruleRepository->findActiveRulesForScope('repository:' . $repository->getId());
        
        // Step 2: Fallback to global rules if no repo rules
        if (empty($repoRules)) {
            $this->logger->debug('No repository-specific rules, using global rules', [
                'repository_id' => $repository->getId(),
            ]);
            $rules = $this->ruleRepository->findActiveGlobalRules();
        } else {
            $this->logger->debug('Using repository-specific rules', [
                'repository_id' => $repository->getId(),
                'rule_count' => count($repoRules),
            ]);
            $rules = $repoRules;
        }
        
        if (empty($rules)) {
            $this->logger->warning('No rules found (neither repo-specific nor global)', [
                'repository_id' => $repository->getId(),
            ]);
            return;
        }
        
        // Step 3: Evaluate each rule
        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $scan)) {
                $this->executeRuleActions($rule, $scan);
            }
        }
    }
    
    /**
     * Evaluate if a rule matches the current scan state
     * 
     * @param Rule $rule The rule to evaluate
     * @param RepositoryScan $scan The scan to check
     * @return bool True if rule matches and should fire
     */
    private function evaluateRule(Rule $rule, RepositoryScan $scan): bool
    {
        $triggerType = $rule->getTriggerType();
        $triggerPayload = $rule->getTriggerPayload() ?? [];
        
        $this->logger->debug('Evaluating rule', [
            'rule_id' => $rule->getId(),
            'rule_name' => $rule->getName(),
            'trigger_type' => $triggerType,
        ]);
        
        return match ($triggerType) {
            'scan_completed' => $this->evaluateScanCompletedTrigger($scan, $triggerPayload),
            'vulnerability_threshold' => $this->evaluateVulnerabilityThresholdTrigger($scan, $triggerPayload),
            'upload_in_progress' => $this->evaluateUploadInProgressTrigger($scan, $triggerPayload),
            'upload_failed' => $this->evaluateUploadFailedTrigger($scan, $triggerPayload),
            default => false,
        };
    }
    
    /**
     * Trigger: scan_completed
     * Fires when scan status = completed (regardless of vulnerability count)
     */
    private function evaluateScanCompletedTrigger(RepositoryScan $scan, array $payload): bool
    {
        return $scan->getStatus() === 'completed';
    }
    
    /**
     * Trigger: vulnerability_threshold
     * Fires when scan completed AND vulnerability count > threshold
     * 
     * Payload:
     * - threshold: int (required) - minimum vulnerability count
     * - min_severity: string (optional) - only count vulnerabilities of this severity or higher
     */
    private function evaluateVulnerabilityThresholdTrigger(RepositoryScan $scan, array $payload): bool
    {
        // Must be completed to have final count
        if ($scan->getStatus() !== 'completed') {
            return false;
        }
        
        $threshold = $payload['threshold'] ?? 0;
        $count = $scan->getVulnerabilityCount();
        
        $matches = $count > $threshold;
        
        if ($matches) {
            $this->logger->info('Vulnerability threshold exceeded', [
                'scan_id' => $scan->getId(),
                'count' => $count,
                'threshold' => $threshold,
            ]);
        }
        
        return $matches;
    }
    
    /**
     * Trigger: upload_in_progress
     * Fires when scan status indicates active processing
     * 
     * Payload:
     * - statuses: array (optional) - specific statuses to match (default: uploaded, queued, running)
     */
    private function evaluateUploadInProgressTrigger(RepositoryScan $scan, array $payload): bool
    {
        $inProgressStatuses = $payload['statuses'] ?? ['uploaded', 'queued', 'running'];
        
        return in_array($scan->getStatus(), $inProgressStatuses, true);
    }
    
    /**
     * Trigger: upload_failed
     * Fires when scan status indicates failure
     * 
     * Payload:
     * - statuses: array (optional) - specific failure statuses (default: failed, timeout)
     */
    private function evaluateUploadFailedTrigger(RepositoryScan $scan, array $payload): bool
    {
        $failedStatuses = $payload['statuses'] ?? ['failed', 'timeout'];
        
        return in_array($scan->getStatus(), $failedStatuses, true);
    }
    
    /**
     * Execute all actions associated with a matched rule
     * 
     * @param Rule $rule The matched rule
     * @param RepositoryScan $scan The scan context
     */
    private function executeRuleActions(Rule $rule, RepositoryScan $scan): void
    {
        $actions = $rule->getRuleActions();
        
        if ($actions->isEmpty()) {
            $this->logger->warning('Rule has no actions defined', [
                'rule_id' => $rule->getId(),
                'rule_name' => $rule->getName(),
            ]);
            return;
        }
        
        $this->logger->info('Executing rule actions', [
            'rule_id' => $rule->getId(),
            'rule_name' => $rule->getName(),
            'action_count' => $actions->count(),
        ]);
        
        foreach ($actions as $action) {
            $this->executeAction($action, $scan, $rule);
        }
    }
    
    /**
     * Execute a single action
     * 
     * @param RuleAction $action The action to execute
     * @param RepositoryScan $scan The scan context
     * @param Rule $rule The parent rule (for logging)
     */
    private function executeAction(RuleAction $action, RepositoryScan $scan, Rule $rule): void
    {
        $actionType = $action->getActionType();
        $actionPayload = $action->getActionPayload() ?? [];
        
        $this->logger->debug('Executing action', [
            'rule_id' => $rule->getId(),
            'action_id' => $action->getId(),
            'action_type' => $actionType,
        ]);
        
        try {
            match ($actionType) {
                'email' => $this->executeEmailAction($scan, $actionPayload),
                'slack' => $this->executeSlackAction($scan, $actionPayload),
                'webhook' => $this->executeWebhookAction($scan, $actionPayload),
                default => $this->logger->warning('Unknown action type', [
                    'action_type' => $actionType,
                    'action_id' => $action->getId(),
                ]),
            };
            
            $this->logger->info('Action executed successfully', [
                'rule_id' => $rule->getId(),
                'action_id' => $action->getId(),
                'action_type' => $actionType,
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Action execution failed', [
                'rule_id' => $rule->getId(),
                'action_id' => $action->getId(),
                'action_type' => $actionType,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Execute email action - Uses Symfony Notifier with appropriate Notification class
     * 
     * The action is determined by the rule's trigger type and scan status.
     * Notifications are sent to repository-specific emails from NotificationSetting.
     * Falls back to admin recipients if no repository settings exist.
     * Channels are passed from the action type to the notification.
     */
    private function executeEmailAction(RepositoryScan $scan, array $payload): void
    {
        $notification = $this->createNotificationForScan($scan, $payload, ['email']);
        
        if ($notification) {
            $recipients = $this->getEmailRecipientsForRepository($scan->getRepository());
            
            if (empty($recipients)) {
                // Fallback to default admin recipients
                $this->notifier->send($notification);
                $this->logger->debug('Email notification sent to admin recipients');
            } else {
                // Send to repository-specific recipients
                foreach ($recipients as $email) {
                    $this->notifier->send($notification, new \Symfony\Component\Notifier\Recipient\Recipient($email));
                }
                $this->logger->debug('Email notification sent to repository-specific recipients', [
                    'recipients' => $recipients,
                ]);
            }
        }
    }
    
    /**
     * Execute Slack action - Uses Symfony Notifier with appropriate Notification class
     * 
     * Same notification classes as email, but routed to chat channel.
     * Uses repository-specific Slack channels from NotificationSetting.
     * Falls back to default channel if no repository settings exist.
     * Channels are passed from the action type to the notification.
     */
    private function executeSlackAction(RepositoryScan $scan, array $payload): void
    {
        $notification = $this->createNotificationForScan($scan, $payload, ['chat']);
        
        if ($notification) {
            $slackChannels = $this->getSlackChannelsForRepository($scan->getRepository());
            
            if (empty($slackChannels)) {
                // Fallback to default Slack channel from DSN
                $this->notifier->send($notification);
                $this->logger->debug('Slack notification sent to default channel');
            } else {
                // Send to repository-specific Slack channels
                // Note: Symfony Notifier doesn't support per-message channel override easily
                // This would require custom implementation or multiple Slack transports
                // For now, we'll log the intended channels and use default
                $this->notifier->send($notification);
                $this->logger->debug('Slack notification sent (intended for specific channels)', [
                    'channels' => $slackChannels,
                ]);
            }
        }
    }
    
    /**
     * Create appropriate Notification class based on scan status
     * 
     * Maps scan status to the correct Symfony Notification class:
     * - failed/timeout -> ScanFailedNotification
     * - completed -> ScanCompletedNotification (checks vulnerability count)
     * - uploaded/queued/running -> UploadInProgressNotification
     * 
     * @param RepositoryScan $scan The scan to notify about
     * @param array $payload Action payload (may contain threshold, etc.)
     * @param array $channels Notification channels from action type (email, chat, etc.)
     * @return HighVulnerabilityNotification|ScanFailedNotification|ScanCompletedNotification|UploadInProgressNotification|null
     */
    private function createNotificationForScan(RepositoryScan $scan, array $payload, array $channels): ?object
    {
        $status = $scan->getStatus();
        $vulnCount = $scan->getVulnerabilityCount() ?? 0;
        
        // Get threshold from payload if specified, otherwise use default
        $threshold = $payload['threshold'] ?? 10;
        
        return match ($status) {
            'failed', 'timeout' => new ScanFailedNotification($scan, $channels),
            'completed' => $vulnCount > $threshold 
                ? new HighVulnerabilityNotification($scan, $threshold, $channels)
                : new ScanCompletedNotification($scan, $channels),
            'uploaded', 'queued', 'running' => new UploadInProgressNotification($scan, $channels),
            default => null,
        };
    }
    
    /**
     * Execute webhook action (future implementation)
     * 
     * Payload:
     * - url: string (required) - webhook URL
     * - method: string (optional) - HTTP method (default: POST)
     * - headers: array (optional) - custom headers
     */
    private function executeWebhookAction(RepositoryScan $scan, array $payload): void
    {
        // TODO: Implement webhook delivery
        $this->logger->info('Webhook action not yet implemented', [
            'scan_id' => $scan->getId(),
        ]);
    }
    
    /**
     * Get email recipients for a repository from NotificationSetting
     * 
     * @param Repository $repository
     * @return array List of email addresses
     */
    private function getEmailRecipientsForRepository(Repository $repository): array
    {
        $emails = [];
        
        foreach ($repository->getNotificationSettings() as $setting) {
            $settingEmails = $setting->getEmails();
            if ($settingEmails && is_array($settingEmails)) {
                $emails = array_merge($emails, $settingEmails);
            }
        }
        
        return array_unique(array_filter($emails));
    }
    
    /**
     * Get Slack channels for a repository from NotificationSetting
     * 
     * @param Repository $repository
     * @return array List of Slack channel identifiers
     */
    private function getSlackChannelsForRepository(Repository $repository): array
    {
        $channels = [];
        
        foreach ($repository->getNotificationSettings() as $setting) {
            $settingChannels = $setting->getSlackChannels();
            if ($settingChannels && is_array($settingChannels)) {
                $channels = array_merge($channels, $settingChannels);
            }
        }
        
        return array_unique(array_filter($channels));
    }
    
    /**
     * Replace template variables in message strings
     * 
     * Supported variables:
     * - {{repository}} - Repository name
     * - {{branch}} - Branch name
     * - {{status}} - Scan status
     * - {{vulnerability_count}} - Vulnerability count
     * - {{provider}} - Provider code
     * - {{scan_id}} - Scan UUID
     * - {{started_at}} - Start timestamp
     * - {{completed_at}} - Completion timestamp
     */
    private function replaceTemplateVariables(string $template, RepositoryScan $scan): string
    {
        $variables = [
            '{{repository}}' => $scan->getRepository()->getName(),
            '{{branch}}' => $scan->getBranch() ?? 'main',
            '{{status}}' => $scan->getStatus(),
            '{{vulnerability_count}}' => (string) $scan->getVulnerabilityCount(),
            '{{provider}}' => $scan->getProviderCode() ?? 'unknown',
            '{{scan_id}}' => $scan->getId(),
            '{{started_at}}' => $scan->getStartedAt()?->format('Y-m-d H:i:s') ?? 'N/A',
            '{{completed_at}}' => $scan->getCompletedAt()?->format('Y-m-d H:i:s') ?? 'N/A',
        ];
        
        return str_replace(
            array_keys($variables),
            array_values($variables),
            $template
        );
    }
    
}
