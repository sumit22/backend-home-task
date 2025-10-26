<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026115517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vulnerability (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', scan_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(1024) NOT NULL, cve VARCHAR(128) DEFAULT NULL, severity VARCHAR(64) DEFAULT NULL, score NUMERIC(5, 2) DEFAULT NULL, fixed_in VARCHAR(256) DEFAULT NULL, `references` JSON DEFAULT NULL, ignored TINYINT(1) NOT NULL, package_name VARCHAR(1024) DEFAULT NULL, package_version VARCHAR(256) DEFAULT NULL, ecosystem VARCHAR(128) DEFAULT NULL, package_metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, INDEX idx_vulnerability_scan (scan_id), INDEX idx_vulnerability_cve (cve), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_6C4E40472827AAD3 FOREIGN KEY (scan_id) REFERENCES repository_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C427744E0351 FOREIGN KEY (rule_id) REFERENCES rule (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C427429EE315 FOREIGN KEY (rule_action_id) REFERENCES rule_action (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C4272827AAD3 FOREIGN KEY (scan_id) REFERENCES repository_scan (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE action_execution ADD CONSTRAINT FK_A6A6C42772897D8B FOREIGN KEY (vulnerability_id) REFERENCES vulnerability (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE api_credential ADD CONSTRAINT FK_C2D5615AA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file_scan_result ADD CONSTRAINT FK_ED601A8F93CB796C FOREIGN KEY (file_id) REFERENCES files_in_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE file_scan_result ADD CONSTRAINT FK_ED601A8FEC68BBB8 FOREIGN KEY (scan_result_id) REFERENCES scan_result (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE files_in_scan ADD CONSTRAINT FK_4FD5939D89C594D2 FOREIGN KEY (repository_scan_id) REFERENCES repository_scan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integration ADD CONSTRAINT FK_FDE96D9BA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_setting ADD CONSTRAINT FK_8A6A322F50C9D4F7 FOREIGN KEY (repository_id) REFERENCES repository (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_repository_name ON repository');
        $this->addSql('ALTER TABLE repository_scan ADD CONSTRAINT FK_779D944650C9D4F7 FOREIGN KEY (repository_id) REFERENCES repository (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rule_action ADD CONSTRAINT FK_DC667C02744E0351 FOREIGN KEY (rule_id) REFERENCES rule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scan_result ADD CONSTRAINT FK_CFDBE4ED89C594D2 FOREIGN KEY (repository_scan_id) REFERENCES repository_scan (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C42772897D8B');
        $this->addSql('ALTER TABLE vulnerability DROP FOREIGN KEY FK_6C4E40472827AAD3');
        $this->addSql('DROP TABLE vulnerability');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C427744E0351');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C427429EE315');
        $this->addSql('ALTER TABLE action_execution DROP FOREIGN KEY FK_A6A6C4272827AAD3');
        $this->addSql('ALTER TABLE api_credential DROP FOREIGN KEY FK_C2D5615AA53A8AA');
        $this->addSql('ALTER TABLE file_scan_result DROP FOREIGN KEY FK_ED601A8F93CB796C');
        $this->addSql('ALTER TABLE file_scan_result DROP FOREIGN KEY FK_ED601A8FEC68BBB8');
        $this->addSql('ALTER TABLE files_in_scan DROP FOREIGN KEY FK_4FD5939D89C594D2');
        $this->addSql('ALTER TABLE integration DROP FOREIGN KEY FK_FDE96D9BA53A8AA');
        $this->addSql('ALTER TABLE notification_setting DROP FOREIGN KEY FK_8A6A322F50C9D4F7');
        $this->addSql('CREATE INDEX idx_repository_name ON repository (name)');
        $this->addSql('ALTER TABLE repository_scan DROP FOREIGN KEY FK_779D944650C9D4F7');
        $this->addSql('ALTER TABLE rule_action DROP FOREIGN KEY FK_DC667C02744E0351');
        $this->addSql('ALTER TABLE scan_result DROP FOREIGN KEY FK_CFDBE4ED89C594D2');
    }
}
