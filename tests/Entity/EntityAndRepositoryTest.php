<?php

namespace App\Tests\Entity;

use App\Entity\ActionExecution;
use App\Entity\ApiCredential;
use App\Entity\FileScanResult;
use App\Entity\FilesInScan;
use App\Entity\Integration;
use App\Entity\NotificationSetting;
use App\Entity\Provider;
use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\Rule;
use App\Entity\RuleAction;
use App\Entity\ScanResult;
use App\Entity\Vulnerability;
use App\Repository\ActionExecutionRepository;
use App\Repository\ApiCredentialRepository;
use App\Repository\FileScanResultRepository;
use App\Repository\FilesInScanRepository;
use App\Repository\IntegrationRepository;
use App\Repository\NotificationSettingRepository;
use App\Repository\ProviderRepository;
use App\Repository\RepositoryRepository;
use App\Repository\RepositoryScanRepository;
use App\Repository\RuleActionRepository;
use App\Repository\RuleRepository;
use App\Repository\ScanResultRepository;
use App\Repository\VulnerabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Comprehensive test for all entities and repositories.
 * Tests basic entity functionality (getters/setters, ID generation, timestamps)
 * and repository instantiation/basic queries.
 */
class EntityAndRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @dataProvider entityProvider
     */
    public function testEntityHasIdAfterConstruction(string $entityClass): void
    {
        $entity = new $entityClass();
        
        $this->assertNotNull($entity->getId(), "Entity {$entityClass} should have ID after construction");
        $this->assertInstanceOf(\Symfony\Component\Uid\Uuid::class, $entity->getId(), "Entity {$entityClass} ID should be Uuid instance");
    }

    /**
     * @dataProvider entityProvider
     */
    public function testEntityHasTimestamps(string $entityClass): void
    {
        $entity = new $entityClass();
        
        // Timestamps should be null initially
        $this->assertNull($entity->getCreatedAt(), "Entity {$entityClass} createdAt should be null initially");
        $this->assertNull($entity->getUpdatedAt(), "Entity {$entityClass} updatedAt should be null initially");
        
        // Simulate prePersist lifecycle event
        $entity->setCreatedAtValue();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt(), "Entity {$entityClass} should have createdAt after setCreatedAtValue");
        
        // Simulate preUpdate lifecycle event
        $entity->setUpdatedAtValue();
        
        $this->assertInstanceOf(\DateTime::class, $entity->getUpdatedAt(), "Entity {$entityClass} should have updatedAt after setUpdatedAtValue");
    }

    /**
     * @dataProvider repositoryProvider
     */
    public function testRepositoryCanBeRetrieved(string $repositoryClass, string $entityClass): void
    {
        $repository = $this->em->getRepository($entityClass);
        
        $this->assertInstanceOf($repositoryClass, $repository, "Repository {$repositoryClass} should be retrievable");
        $this->assertNotNull($repository, "Repository {$repositoryClass} should not be null");
    }

    /**
     * @dataProvider repositoryProvider
     */
    public function testRepositoryHasBasicMethods(string $repositoryClass, string $entityClass): void
    {
        $repository = $this->em->getRepository($entityClass);
        
        $this->assertTrue(method_exists($repository, 'find'), "Repository {$repositoryClass} should have find() method");
        $this->assertTrue(method_exists($repository, 'findAll'), "Repository {$repositoryClass} should have findAll() method");
        $this->assertTrue(method_exists($repository, 'findBy'), "Repository {$repositoryClass} should have findBy() method");
        $this->assertTrue(method_exists($repository, 'findOneBy'), "Repository {$repositoryClass} should have findOneBy() method");
    }

    public function testRepositoryEntity(): void
    {
        $repo = new Repository();
        
        $repo->setName('test-repo');
        $this->assertSame('test-repo', $repo->getName());
        
        $repo->setUrl('https://github.com/test/repo');
        $this->assertSame('https://github.com/test/repo', $repo->getUrl());
        
        $repo->setDefaultBranch('main');
        $this->assertSame('main', $repo->getDefaultBranch());
        
        $settings = ['webhook' => 'enabled'];
        $repo->setSettings($settings);
        $this->assertSame($settings, $repo->getSettings());
        
        // Test collections
        $this->assertCount(0, $repo->getNotificationSettings());
        $this->assertCount(0, $repo->getRepositoryScans());
    }

    public function testProviderEntity(): void
    {
        $provider = new Provider();
        
        $provider->setCode('debricked');
        $this->assertSame('debricked', $provider->getCode());
        
        $provider->setName('Debricked');
        $this->assertSame('Debricked', $provider->getName());
        
        $config = ['api_url' => 'https://debricked.com/api'];
        $provider->setConfig($config);
        $this->assertSame($config, $provider->getConfig());
        
        // Test collections
        $this->assertCount(0, $provider->getApiCredentials());
    }

    public function testRepositoryScanEntity(): void
    {
        $scan = new RepositoryScan();
        
        $scan->setBranch('main');
        $this->assertSame('main', $scan->getBranch());
        
        $scan->setRequestedBy('admin@example.com');
        $this->assertSame('admin@example.com', $scan->getRequestedBy());
        
        $scan->setStatus('completed');
        $this->assertSame('completed', $scan->getStatus());
        
        $scan->setScanType('full');
        $this->assertSame('full', $scan->getScanType());
        
        $scan->setScannerVersion('1.0.0');
        $this->assertSame('1.0.0', $scan->getScannerVersion());
        
        $scan->setVulnerabilityCount(5);
        $this->assertSame(5, $scan->getVulnerabilityCount());
        
        $summary = ['total' => 5, 'high' => 2];
        $scan->setRawSummary($summary);
        $this->assertSame($summary, $scan->getRawSummary());
        
        $startedAt = new \DateTime('2025-10-28 10:00:00');
        $scan->setStartedAt($startedAt);
        $this->assertSame($startedAt, $scan->getStartedAt());
        
        $completedAt = new \DateTime('2025-10-28 11:00:00');
        $scan->setCompletedAt($completedAt);
        $this->assertSame($completedAt, $scan->getCompletedAt());
        
        // Test relationships
        $repo = new Repository();
        $scan->setRepository($repo);
        $this->assertSame($repo, $scan->getRepository());
        
        $scan->setProviderCode('debricked');
        $this->assertSame('debricked', $scan->getProviderCode());
        
        // Test collections
        $this->assertCount(0, $scan->getFilesInScans());
        $this->assertCount(0, $scan->getVulnerabilities());
    }

    public function testVulnerabilityEntity(): void
    {
        $vuln = new Vulnerability();
        
        $vuln->setPackageName('symfony/http-kernel');
        $this->assertSame('symfony/http-kernel', $vuln->getPackageName());
        
        $vuln->setPackageVersion('5.4.0');
        $this->assertSame('5.4.0', $vuln->getPackageVersion());
        
        $vuln->setTitle('XSS vulnerability in HttpKernel');
        $this->assertSame('XSS vulnerability in HttpKernel', $vuln->getTitle());
        
        $vuln->setCve('CVE-2023-1234');
        $this->assertSame('CVE-2023-1234', $vuln->getCve());
        
        $vuln->setSeverity('high');
        $this->assertSame('high', $vuln->getSeverity());
        
        $vuln->setScore('7.5');
        $this->assertSame('7.5', $vuln->getScore());
        
        $vuln->setFixedIn('5.4.25');
        $this->assertSame('5.4.25', $vuln->getFixedIn());
        
        $references = ['https://cve.org/CVE-2023-1234'];
        $vuln->setReferences($references);
        $this->assertSame($references, $vuln->getReferences());
        
        $vuln->setIgnored(false);
        $this->assertFalse($vuln->isIgnored());
        
        $vuln->setEcosystem('packagist');
        $this->assertSame('packagist', $vuln->getEcosystem());
        
        $metadata = ['cvss' => 7.5];
        $vuln->setPackageMetadata($metadata);
        $this->assertSame($metadata, $vuln->getPackageMetadata());
        
        // Test relationship
        $scan = new RepositoryScan();
        $vuln->setScan($scan);
        $this->assertSame($scan, $vuln->getScan());
    }

    public function testFilesInScanEntity(): void
    {
        $file = new FilesInScan();
        
        $file->setFileName('composer.lock');
        $this->assertSame('composer.lock', $file->getFileName());
        
        $file->setFilePath('/path/to/composer.lock');
        $this->assertSame('/path/to/composer.lock', $file->getFilePath());
        
        $file->setSize(1024);
        $this->assertSame(1024, $file->getSize());
        
        $file->setMimeType('application/json');
        $this->assertSame('application/json', $file->getMimeType());
        
        $file->setContentHash('abc123def456');
        $this->assertSame('abc123def456', $file->getContentHash());
        
        $file->setStatus('scanned');
        $this->assertSame('scanned', $file->getStatus());
        
        // Test relationship
        $scan = new RepositoryScan();
        $file->setRepositoryScan($scan);
        $this->assertSame($scan, $file->getRepositoryScan());
        
        // Test collection
        $this->assertCount(0, $file->getFileScanResults());
    }

    public function testIntegrationEntity(): void
    {
        $integration = new Integration();
        
        $integration->setExternalId('github_123456');
        $this->assertSame('github_123456', $integration->getExternalId());
        
        $integration->setType('repository');
        $this->assertSame('repository', $integration->getType());
        
        $integration->setLinkedEntityType('Repository');
        $this->assertSame('Repository', $integration->getLinkedEntityType());
        
        $integration->setStatus('active');
        $this->assertSame('active', $integration->getStatus());
        
        $payload = ['webhook_url' => 'https://example.com/webhook'];
        $integration->setRawPayload($payload);
        $this->assertSame($payload, $integration->getRawPayload());
        
        // Test provider_code
        $integration->setProviderCode('debricked');
        $this->assertSame('debricked', $integration->getProviderCode());
    }

    public function testApiCredentialEntity(): void
    {
        $credential = new ApiCredential();
        
        $credentialData = ['token' => 'secret-token-123', 'type' => 'bearer'];
        $credential->setCredentialData($credentialData);
        $this->assertSame($credentialData, $credential->getCredentialData());
        
        $rotatedAt = new \DateTime('2025-10-28');
        $credential->setLastRotatedAt($rotatedAt);
        $this->assertSame($rotatedAt, $credential->getLastRotatedAt());
        
        // Test relationship
        $provider = new Provider();
        $credential->setProvider($provider);
        $this->assertSame($provider, $credential->getProvider());
    }

    public function testNotificationSettingEntity(): void
    {
        $setting = new NotificationSetting();
        
        $emails = ['admin@example.com', 'dev@example.com'];
        $setting->setEmails($emails);
        $this->assertSame($emails, $setting->getEmails());
        
        $slackChannels = ['#alerts', '#security'];
        $setting->setSlackChannels($slackChannels);
        $this->assertSame($slackChannels, $setting->getSlackChannels());
        
        $webhooks = ['https://example.com/webhook'];
        $setting->setWebhooks($webhooks);
        $this->assertSame($webhooks, $setting->getWebhooks());
        
        // Test relationship
        $repo = new Repository();
        $setting->setRepository($repo);
        $this->assertSame($repo, $setting->getRepository());
    }

    public function testRuleEntity(): void
    {
        $rule = new Rule();
        
        $rule->setName('Critical Vulnerability Rule');
        $this->assertSame('Critical Vulnerability Rule', $rule->getName());
        
        $rule->setEnabled(true);
        $this->assertTrue($rule->isEnabled());
        
        $rule->setTriggerType('vulnerability_detected');
        $this->assertSame('vulnerability_detected', $rule->getTriggerType());
        
        $triggerPayload = ['severity' => 'critical'];
        $rule->setTriggerPayload($triggerPayload);
        $this->assertSame($triggerPayload, $rule->getTriggerPayload());
        
        $rule->setScope('repository');
        $this->assertSame('repository', $rule->getScope());
        
        $rule->setAutoRemediation(true);
        $this->assertTrue($rule->isAutoRemediation());
        
        $remediationConfig = ['action' => 'create_issue'];
        $rule->setRemediationConfig($remediationConfig);
        $this->assertSame($remediationConfig, $rule->getRemediationConfig());
        
        // Test collection
        $this->assertCount(0, $rule->getRuleActions());
    }

    public function testRuleActionEntity(): void
    {
        $action = new RuleAction();
        
        $action->setActionType('send_notification');
        $this->assertSame('send_notification', $action->getActionType());
        
        $params = ['channel' => 'email', 'recipients' => ['admin@example.com']];
        $action->setActionPayload($params);
        $this->assertSame($params, $action->getActionPayload());
        
        // Test relationship
        $rule = new Rule();
        $action->setRule($rule);
        $this->assertSame($rule, $action->getRule());
    }

    public function testActionExecutionEntity(): void
    {
        $execution = new ActionExecution();
        
        $execution->setStatus('success');
        $this->assertSame('success', $execution->getStatus());
        
        $result = ['message' => 'Notification sent successfully'];
        $execution->setResultPayload($result);
        $this->assertSame($result, $execution->getResultPayload());
        
        $finishedAt = new \DateTime('2025-10-28 12:00:00');
        $execution->setFinishedAt($finishedAt);
        $this->assertSame($finishedAt, $execution->getFinishedAt());
        
        // Test relationships
        $rule = new Rule();
        $execution->setRule($rule);
        $this->assertSame($rule, $execution->getRule());
        
        $action = new RuleAction();
        $execution->setRuleAction($action);
        $this->assertSame($action, $execution->getRuleAction());
        
        $scan = new RepositoryScan();
        $execution->setScan($scan);
        $this->assertSame($scan, $execution->getScan());
        
        $vulnerability = new Vulnerability();
        $execution->setVulnerability($vulnerability);
        $this->assertSame($vulnerability, $execution->getVulnerability());
    }

    public function testScanResultEntity(): void
    {
        $result = new ScanResult();
        
        $result->setVulnerabilityCount(10);
        $this->assertSame(10, $result->getVulnerabilityCount());
        
        $result->setStatus('completed');
        $this->assertSame('completed', $result->getStatus());
        
        $summaryJson = [
            'total' => 10,
            'critical' => 2,
            'high' => 3,
            'medium' => 4,
            'low' => 1
        ];
        $result->setSummaryJson($summaryJson);
        $this->assertSame($summaryJson, $result->getSummaryJson());
        
        // Test relationship
        $scan = new RepositoryScan();
        $result->setRepositoryScan($scan);
        $this->assertSame($scan, $result->getRepositoryScan());
        
        // Test collection
        $this->assertCount(0, $result->getFileScanResults());
    }

    public function testFileScanResultEntity(): void
    {
        $result = new FileScanResult();
        
        $result->setStatus('completed');
        $this->assertSame('completed', $result->getStatus());
        
        $rawPayload = ['scanner' => 'debricked', 'scan_time' => 123];
        $result->setRawPayload($rawPayload);
        $this->assertSame($rawPayload, $result->getRawPayload());
        
        // Test relationships
        $file = new FilesInScan();
        $result->setFile($file);
        $this->assertSame($file, $result->getFile());
        
        $scanResult = new ScanResult();
        $result->setScanResult($scanResult);
        $this->assertSame($scanResult, $result->getScanResult());
    }

    /**
     * Provider for all entity classes that should have ID and timestamps
     */
    public function entityProvider(): array
    {
        return [
            'Repository' => [Repository::class],
            'Provider' => [Provider::class],
            'RepositoryScan' => [RepositoryScan::class],
            'Vulnerability' => [Vulnerability::class],
            'FilesInScan' => [FilesInScan::class],
            'Integration' => [Integration::class],
            'ApiCredential' => [ApiCredential::class],
            'NotificationSetting' => [NotificationSetting::class],
            'Rule' => [Rule::class],
            'RuleAction' => [RuleAction::class],
            'ActionExecution' => [ActionExecution::class],
            'ScanResult' => [ScanResult::class],
            'FileScanResult' => [FileScanResult::class],
        ];
    }

    /**
     * Provider for all repository classes
     */
    public function repositoryProvider(): array
    {
        return [
            'RepositoryRepository' => [RepositoryRepository::class, Repository::class],
            'ProviderRepository' => [ProviderRepository::class, Provider::class],
            'RepositoryScanRepository' => [RepositoryScanRepository::class, RepositoryScan::class],
            'VulnerabilityRepository' => [VulnerabilityRepository::class, Vulnerability::class],
            'FilesInScanRepository' => [FilesInScanRepository::class, FilesInScan::class],
            'IntegrationRepository' => [IntegrationRepository::class, Integration::class],
            'ApiCredentialRepository' => [ApiCredentialRepository::class, ApiCredential::class],
            'NotificationSettingRepository' => [NotificationSettingRepository::class, NotificationSetting::class],
            'RuleRepository' => [RuleRepository::class, Rule::class],
            'RuleActionRepository' => [RuleActionRepository::class, RuleAction::class],
            'ActionExecutionRepository' => [ActionExecutionRepository::class, ActionExecution::class],
            'ScanResultRepository' => [ScanResultRepository::class, ScanResult::class],
            'FileScanResultRepository' => [FileScanResultRepository::class, FileScanResult::class],
        ];
    }
}
