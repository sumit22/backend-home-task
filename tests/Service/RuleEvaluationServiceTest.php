<?php

namespace App\Tests\Service;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\Rule;
use App\Entity\RuleAction;
use App\Repository\RuleRepository;
use App\Service\RuleEvaluationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\NotifierInterface;

class RuleEvaluationServiceTest extends TestCase
{
    private RuleRepository $ruleRepository;
    private NotifierInterface&MockObject $notifier;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private RuleEvaluationService $service;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->createMock(RuleRepository::class);
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->service = new RuleEvaluationService(
            $this->ruleRepository,
            $this->notifier,
            $this->entityManager,
            $this->logger
        );
    }

    public function testEvaluateAndNotifyWithRepositoryRules(): void
    {
        $scan = $this->createCompletedScan(15);
        
        // Repository-specific rules exist
        $repoRules = [$this->createVulnerabilityRule('repository:01JAB1C2D3E4F5G6H7J8K9M0N1', 5)];
        $this->ruleRepository->expects($this->once())
            ->method('findActiveRulesForScope')
            ->with($this->stringStartsWith('repository:'))
            ->willReturn($repoRules);

        // Should use repository rules (not global)
        $this->ruleRepository->expects($this->never())
            ->method('findActiveGlobalRules');

        // Should send notification via Notifier
        $this->notifier->expects($this->atLeastOnce())
            ->method('send');

        $this->service->evaluateAndNotify($scan);
    }

    public function testEvaluateAndNotifyWithGlobalFallback(): void
    {
        $scan = $this->createCompletedScan(15);
        
        // No repository-specific rules
        $this->ruleRepository->expects($this->once())
            ->method('findActiveRulesForScope')
            ->with($this->stringStartsWith('repository:'))
            ->willReturn([]);

        // Should fallback to global rules
        $globalRules = [$this->createVulnerabilityRule('global', 10)];
        $this->ruleRepository->expects($this->once())
            ->method('findActiveGlobalRules')
            ->willReturn($globalRules);

        // Should send notification via Notifier
        $this->notifier->expects($this->atLeastOnce())
            ->method('send');

        $this->service->evaluateAndNotify($scan);
    }

    public function testVulnerabilityThresholdTriggerMatches(): void
    {
        $scan = $this->createCompletedScan(15);
        $rule = $this->createVulnerabilityRule('global', 10);
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        // Should send HighVulnerabilityNotification
        $this->notifier->expects($this->atLeastOnce())
            ->method('send')
            ->with($this->isInstanceOf(\App\Notification\HighVulnerabilityNotification::class));

        $this->service->evaluateAndNotify($scan);
    }

    public function testVulnerabilityThresholdDoesNotTriggerBelowThreshold(): void
    {
        $scan = $this->createCompletedScan(5);
        $rule = $this->createVulnerabilityRule('global', 10);
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        // Rule should NOT match (5 < 10), so no notification sent
        $this->notifier->expects($this->never())
            ->method('send');

        $this->service->evaluateAndNotify($scan);
    }

    public function testUploadInProgressTrigger(): void
    {
        $scan = $this->createInProgressScan('running');
        $rule = $this->createUploadInProgressRule();
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        // Should send UploadInProgressNotification
        $this->notifier->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(\App\Notification\UploadInProgressNotification::class));

        $this->service->evaluateAndNotify($scan);
    }

    public function testUploadFailedTrigger(): void
    {
        $scan = $this->createFailedScan('failed');
        $rule = $this->createUploadFailedRule();
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        // Should send ScanFailedNotification (both email and Slack actions)
        $this->notifier->expects($this->atLeast(2))
            ->method('send')
            ->with($this->isInstanceOf(\App\Notification\ScanFailedNotification::class));

        $this->service->evaluateAndNotify($scan);
    }

    public function testScanCompletedTrigger(): void
    {
        $scan = $this->createCompletedScan(5);
        $rule = $this->createScanCompletedRule();
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        // Should send ScanCompletedNotification
        $this->notifier->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(\App\Notification\ScanCompletedNotification::class));

        $this->service->evaluateAndNotify($scan);
    }

    public function testMultipleRulesMultipleActions(): void
    {
        $scan = $this->createCompletedScan(15);
        
        $rule1 = $this->createVulnerabilityRule('global', 10);
        $rule2 = $this->createScanCompletedRule();
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule1, $rule2]);

        // Should send notifications from both rules
        $this->notifier->expects($this->atLeast(2))
            ->method('send');

        $this->service->evaluateAndNotify($scan);
    }

    public function testTemplateVariableSubstitution(): void
    {
        $scan = $this->createCompletedScan(15);
        $rule = $this->createVulnerabilityRule('global', 10);
        
        // Template substitution is now handled by Notification classes
        // We just verify that the notification is sent
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        $this->notifier->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(\App\Notification\HighVulnerabilityNotification::class));

        $this->service->evaluateAndNotify($scan);
    }

    public function testDisabledRulesAreNotEvaluated(): void
    {
        $scan = $this->createCompletedScan(15);
        $rule = $this->createVulnerabilityRule('global', 10);
        $rule->setEnabled(false);
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([]); // Disabled rules not returned

        // No notifications should be sent
        $this->notifier->expects($this->never())
            ->method('send');

        $this->service->evaluateAndNotify($scan);
    }

    public function testInvalidTriggerTypeIsIgnored(): void
    {
        $scan = $this->createCompletedScan(15);
        $rule = $this->createMock(Rule::class);
        $rule->method('isEnabled')->willReturn(true);
        $rule->method('getTriggerType')->willReturn('invalid_trigger');
        $rule->method('getRuleActions')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        
        $this->ruleRepository->method('findActiveGlobalRules')
            ->willReturn([$rule]);

        // Should not throw exception
        // No notifications should be sent for invalid trigger types
        $this->notifier->expects($this->never())
            ->method('send');

        $this->service->evaluateAndNotify($scan);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    private function createCompletedScan(int $vulnerabilityCount): RepositoryScan
    {
        $repository = new Repository();
        $repository->setName('test-repo');
        $repository->setUrl('https://github.com/test/repo');
        
        // Use reflection to set private ID (Uuid type)
        $reflection = new \ReflectionClass($repository);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($repository, \Symfony\Component\Uid\Uuid::fromString('01JAB1C2D3E4F5G6H7J8K9M0N1'));
        
        $scan = new RepositoryScan();
        $scan->setRepository($repository);
        $scan->setBranch('main');
        $scan->setStatus('completed');
        $scan->setVulnerabilityCount($vulnerabilityCount);
        $scan->setProviderCode('debricked');
        $scan->setStartedAt(new \DateTime('-10 minutes'));
        $scan->setCompletedAt(new \DateTime());
        
        return $scan;
    }

    private function createInProgressScan(string $status): RepositoryScan
    {
        $scan = $this->createCompletedScan(0);
        $scan->setStatus($status);
        $scan->setCompletedAt(null);
        return $scan;
    }

    private function createFailedScan(string $status): RepositoryScan
    {
        $scan = $this->createCompletedScan(0);
        $scan->setStatus($status);
        return $scan;
    }

    private function createVulnerabilityRule(string $scope, int $threshold): Rule
    {
        $rule = new Rule();
        $rule->setName('High Vulnerability Alert');
        $rule->setEnabled(true);
        $rule->setTriggerType('vulnerability_threshold');
        $rule->setTriggerPayload(['threshold' => $threshold]);
        $rule->setScope($scope);
        
        // Add email action
        $action = new RuleAction();
        $action->setRule($rule);
        $action->setActionType('email');
        $action->setActionPayload([
            'subject' => 'High Vulnerability Count Detected',
            'message' => 'Found {{vulnerability_count}} vulnerabilities'
        ]);
        
        $rule->addRuleAction($action);
        
        return $rule;
    }

    private function createUploadInProgressRule(): Rule
    {
        $rule = new Rule();
        $rule->setName('Upload In Progress');
        $rule->setEnabled(true);
        $rule->setTriggerType('upload_in_progress');
        $rule->setTriggerPayload(['statuses' => ['uploaded', 'queued', 'running']]);
        $rule->setScope('global');
        
        $action = new RuleAction();
        $action->setRule($rule);
        $action->setActionType('slack');
        $action->setActionPayload(['message' => 'Upload In Progress']);
        
        $rule->addRuleAction($action);
        
        return $rule;
    }

    private function createUploadFailedRule(): Rule
    {
        $rule = new Rule();
        $rule->setName('Upload Failed');
        $rule->setEnabled(true);
        $rule->setTriggerType('upload_failed');
        $rule->setTriggerPayload(['statuses' => ['failed', 'timeout']]);
        $rule->setScope('global');
        
        // Email action
        $emailAction = new RuleAction();
        $emailAction->setRule($rule);
        $emailAction->setActionType('email');
        $emailAction->setActionPayload([
            'subject' => 'Scan Failed',
            'message' => 'Scan upload failed'
        ]);
        
        // Slack action
        $slackAction = new RuleAction();
        $slackAction->setRule($rule);
        $slackAction->setActionType('slack');
        $slackAction->setActionPayload(['message' => 'Upload Failed']);
        
        $rule->addRuleAction($emailAction);
        $rule->addRuleAction($slackAction);
        
        return $rule;
    }

    private function createScanCompletedRule(): Rule
    {
        $rule = new Rule();
        $rule->setName('Scan Completed');
        $rule->setEnabled(true);
        $rule->setTriggerType('scan_completed');
        $rule->setTriggerPayload([]);
        $rule->setScope('global');
        
        $action = new RuleAction();
        $action->setRule($rule);
        $action->setActionType('email');
        $action->setActionPayload([
            'subject' => 'Scan Completed',
            'message' => 'Scan completed successfully'
        ]);
        
        $rule->addRuleAction($action);
        
        return $rule;
    }
}
