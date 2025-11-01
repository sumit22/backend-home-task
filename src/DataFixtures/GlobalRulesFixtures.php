<?php

namespace App\DataFixtures;

use App\Entity\Rule;
use App\Entity\RuleAction;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * GlobalRulesFixtures
 * 
 * Seeds the database with default global rules (like hard-coding but in DB)
 * 
 * These 3 rules implement the assignment requirements:
 * 1. High Vulnerability Count → Email + Slack (via HighVulnerabilityNotification)
 * 2. Upload In Progress → Slack (via UploadInProgressNotification)
 * 3. Upload Failed → Email + Slack (via ScanFailedNotification)
 * 
 * Message formatting is handled by Symfony Notification classes, not templates in DB.
 * 
 * To load fixtures:
 * docker compose exec php php bin/console doctrine:fixtures:load --append
 */
class GlobalRulesFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Read vulnerability threshold from environment variable, default to 10 (as per requirements)
        $vulnerabilityThreshold = (int) ($_ENV['VULNERABILITY_THRESHOLD'] ?? 10);
        
        // ============================================================================
        // RULE 1: High Vulnerability Count Alert
        // ============================================================================
        
        $rule1 = new Rule();
        $rule1->setName('Global: High Vulnerability Count Alert');
        $rule1->setEnabled(true);
        $rule1->setTriggerType('vulnerability_threshold');
        $rule1->setTriggerPayload([
            'threshold' => $vulnerabilityThreshold,  // Alert if more than X vulnerabilities
        ]);
        $rule1->setScope('global');
        $rule1->setAutoRemediation(false);
        
        // Action 1a: Send Email (HighVulnerabilityNotification with 'email' channel)
        $action1a = new RuleAction();
        $action1a->setRule($rule1);
        $action1a->setActionType('email');
        $action1a->setActionPayload([
            'threshold' => $vulnerabilityThreshold,  // Passed to HighVulnerabilityNotification
        ]);
        
        // Action 1b: Send Slack (HighVulnerabilityNotification with 'chat' channel)
        $action1b = new RuleAction();
        $action1b->setRule($rule1);
        $action1b->setActionType('slack');
        $action1b->setActionPayload([
            'threshold' => $vulnerabilityThreshold,  // Passed to HighVulnerabilityNotification
        ]);
        
        $manager->persist($rule1);
        $manager->persist($action1a);
        $manager->persist($action1b);
        
        // ============================================================================
        // RULE 2: Upload In Progress Notification
        // ============================================================================
        
        $rule2 = new Rule();
        $rule2->setName('Global: Upload In Progress Notification');
        $rule2->setEnabled(true);
        $rule2->setTriggerType('upload_in_progress');
        $rule2->setTriggerPayload([
            'statuses' => ['uploaded', 'queued', 'running'],
        ]);
        $rule2->setScope('global');
        $rule2->setAutoRemediation(false);
        
        // Action 2: Send Slack only (UploadInProgressNotification with 'chat' channel)
        // Email would be too noisy for in-progress updates
        $action2 = new RuleAction();
        $action2->setRule($rule2);
        $action2->setActionType('slack');
        $action2->setActionPayload([]);  // No custom config needed, channel determined by action type
        
        $manager->persist($rule2);
        $manager->persist($action2);
        
        // ============================================================================
        // RULE 3: Upload Failed Alert
        // ============================================================================
        
        $rule3 = new Rule();
        $rule3->setName('Global: Upload Failed Alert');
        $rule3->setEnabled(true);
        $rule3->setTriggerType('upload_failed');
        $rule3->setTriggerPayload([
            'statuses' => ['failed', 'timeout'],
        ]);
        $rule3->setScope('global');
        $rule3->setAutoRemediation(false);
        
        // Action 3a: Send Email (ScanFailedNotification with 'email' channel)
        $action3a = new RuleAction();
        $action3a->setRule($rule3);
        $action3a->setActionType('email');
        $action3a->setActionPayload([]);  // No custom config needed, channel determined by action type
        
        // Action 3b: Send Slack (ScanFailedNotification with 'chat' channel)
        $action3b = new RuleAction();
        $action3b->setRule($rule3);
        $action3b->setActionType('slack');
        $action3b->setActionPayload([]);  // No custom config needed, channel determined by action type
        
        $manager->persist($rule3);
        $manager->persist($action3a);
        $manager->persist($action3b);
        
        // ============================================================================
        // RULE 4: Scan Completed with No Vulnerabilities - Congratulations!
        // ============================================================================
        
        $rule4 = new Rule();
        $rule4->setName('Global: Clean Scan Congratulations');
        $rule4->setEnabled(true);
        $rule4->setTriggerType('scan_completed_clean');
        $rule4->setTriggerPayload([
            'max_vulnerabilities' => 0,  // Only trigger when exactly 0 vulnerabilities
        ]);
        $rule4->setScope('global');
        $rule4->setAutoRemediation(false);
        
        // Action 4a: Send Congratulatory Email (ScanCompletedNotification with 'email' channel)
        $action4a = new RuleAction();
        $action4a->setRule($rule4);
        $action4a->setActionType('email');
        $action4a->setActionPayload([]);  // No custom config needed
        
        // Action 4b: Send Congratulatory Slack (ScanCompletedNotification with 'chat' channel)
        $action4b = new RuleAction();
        $action4b->setRule($rule4);
        $action4b->setActionType('slack');
        $action4b->setActionPayload([]);  // No custom config needed
        
        $manager->persist($rule4);
        $manager->persist($action4a);
        $manager->persist($action4b);
        
        // Flush all to database
        $manager->flush();
        
        // Log success
        echo "\n✅ Successfully loaded 4 global rules:\n";
        echo "   1. High Vulnerability Count Alert (threshold: {$vulnerabilityThreshold})\n";
        echo "      → HighVulnerabilityNotification (Email + Slack)\n";
        echo "   2. Upload In Progress Notification\n";
        echo "      → UploadInProgressNotification (Slack only)\n";
        echo "   3. Upload Failed Alert\n";
        echo "      → ScanFailedNotification (Email + Slack)\n";
        echo "   4. Clean Scan Congratulations (0 vulnerabilities)\n";
        echo "      → ScanCompletedNotification (Email + Slack)\n\n";
        echo "Message formatting handled by Symfony Notification classes.\n";
        echo "These rules apply globally as fallback for all repositories.\n";
        echo "Create repository-specific rules to override for individual repos.\n";
        echo "Note: Vulnerability threshold can be configured via VULNERABILITY_THRESHOLD env variable (default: 10).\n\n";
    }
}
