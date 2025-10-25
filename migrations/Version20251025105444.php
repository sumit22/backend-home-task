<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025105444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE integration (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', provider_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', external_id VARCHAR(1024) NOT NULL, type VARCHAR(64) NOT NULL, linked_entity_type VARCHAR(128) DEFAULT NULL, linked_entity_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', status VARCHAR(64) DEFAULT NULL, raw_payload JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, INDEX idx_integration_provider (provider_id), INDEX idx_integration_linked (linked_entity_type, linked_entity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_setting (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', repository_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', channel VARCHAR(64) NOT NULL, config JSON DEFAULT NULL, enabled TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8A6A322F50C9D4F7 (repository_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE provider (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(512) NOT NULL, type VARCHAR(128) NOT NULL, config JSON DEFAULT NULL, enabled TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE repository (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', provider_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(1024) NOT NULL, full_path VARCHAR(2048) DEFAULT NULL, default_branch VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, INDEX IDX_5CFE57CDA53A8AA (provider_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE repository_scan (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', repository_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', commit_sha VARCHAR(255) DEFAULT NULL, branch VARCHAR(255) DEFAULT NULL, status VARCHAR(64) NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, INDEX IDX_779D944650C9D4F7 (repository_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rule (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(512) NOT NULL, enabled TINYINT(1) NOT NULL, trigger_type VARCHAR(128) NOT NULL, trigger_payload JSON DEFAULT NULL, scope VARCHAR(128) DEFAULT NULL, auto_remediation TINYINT(1) NOT NULL, remediation_config JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rule_action (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', rule_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', action_type VARCHAR(64) NOT NULL, action_payload JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_rule_action_rule (rule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scan_result (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', repository_scan_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', summary_json JSON DEFAULT NULL, status VARCHAR(64) NOT NULL, vulnerability_count INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CFDBE4ED89C594D2 (repository_scan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vulnerability (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', scan_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(1024) NOT NULL, cve VARCHAR(128) DEFAULT NULL, severity VARCHAR(64) DEFAULT NULL, score NUMERIC(5, 2) DEFAULT NULL, fixed_in VARCHAR(256) DEFAULT NULL, `references` JSON DEFAULT NULL, ignored TINYINT(1) NOT NULL, package_name VARCHAR(1024) DEFAULT NULL, package_version VARCHAR(256) DEFAULT NULL, ecosystem VARCHAR(128) DEFAULT NULL, package_metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, INDEX idx_vulnerability_scan (scan_id), INDEX idx_vulnerability_cve (cve), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE integration ADD CONSTRAINT FK_FDE96D9BA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_setting ADD CONSTRAINT FK_8A6A322F50C9D4F7 FOREIGN KEY (repository_id) REFERENCES repository (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repository ADD CONSTRAINT FK_5CFE57CDA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repository_scan ADD CONSTRAINT FK_779D944650C9D4F7 FOREIGN KEY (repository_id) REFERENCES repository (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rule_action ADD CONSTRAINT FK_DC667C02744E0351 FOREIGN KEY (rule_id) REFERENCES rule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scan_result ADD CONSTRAINT FK_CFDBE4ED89C594D2 FOREIGN KEY (repository_scan_id) REFERENCES repository_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_6C4E40472827AAD3 FOREIGN KEY (scan_id) REFERENCES repository_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C427744E0351 FOREIGN KEY (rule_id) REFERENCES rule (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C427429EE315 FOREIGN KEY (rule_action_id) REFERENCES rule_action (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C4272827AAD3 FOREIGN KEY (scan_id) REFERENCES repository_scan (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C42772897D8B FOREIGN KEY (vulnerability_id) REFERENCES vulnerability (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE api_credential ADD CONSTRAINT FK_C2D5615AA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file_scan_result ADD CONSTRAINT FK_ED601A8F93CB796C FOREIGN KEY (file_id) REFERENCES files_in_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file_scan_result ADD CONSTRAINT FK_ED601A8FEC68BBB8 FOREIGN KEY (scan_result_id) REFERENCES scan_result (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE files_in_scan ADD CONSTRAINT FK_4FD5939D2827AAD3 FOREIGN KEY (scan_id) REFERENCES repository_scan (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_credential DROP FOREIGN KEY FK_C2D5615AA53A8AA');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C4272827AAD3');
        $this->addSql('ALTER TABLE files_in_scan DROP FOREIGN KEY FK_4FD5939D2827AAD3');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C427744E0351');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C427429EE315');
        $this->addSql('ALTER TABLE file_scan_result DROP FOREIGN KEY FK_ED601A8FEC68BBB8');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C42772897D8B');
        $this->addSql('ALTER TABLE integration DROP FOREIGN KEY FK_FDE96D9BA53A8AA');
        $this->addSql('ALTER TABLE notification_setting DROP FOREIGN KEY FK_8A6A322F50C9D4F7');
        $this->addSql('ALTER TABLE repository DROP FOREIGN KEY FK_5CFE57CDA53A8AA');
        $this->addSql('ALTER TABLE repository_scan DROP FOREIGN KEY FK_779D944650C9D4F7');
        $this->addSql('ALTER TABLE rule_action DROP FOREIGN KEY FK_DC667C02744E0351');
        $this->addSql('ALTER TABLE scan_result DROP FOREIGN KEY FK_CFDBE4ED89C594D2');
        $this->addSql('ALTER TABLE vulnerability DROP FOREIGN KEY FK_6C4E40472827AAD3');
        $this->addSql('DROP TABLE integration');
        $this->addSql('DROP TABLE notification_setting');
        $this->addSql('DROP TABLE provider');
        $this->addSql('DROP TABLE repository');
        $this->addSql('DROP TABLE repository_scan');
        $this->addSql('DROP TABLE rule');
        $this->addSql('DROP TABLE rule_action');
        $this->addSql('DROP TABLE scan_result');
        $this->addSql('DROP TABLE vulnerability');
        $this->addSql('ALTER TABLE file_scan_result DROP FOREIGN KEY FK_ED601A8F93CB796C');
    }
}
