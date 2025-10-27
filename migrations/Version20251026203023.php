<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026203023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_repository_scan_provider ON repository_scan');
        $this->addSql('ALTER TABLE repository_scan ADD provider_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', DROP provider_selection');
        $this->addSql('ALTER TABLE repository_scan ADD CONSTRAINT FK_779D9446A53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_repository_scan_provider ON repository_scan (provider_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE repository_scan DROP FOREIGN KEY FK_779D9446A53A8AA');
        $this->addSql('DROP INDEX idx_repository_scan_provider ON repository_scan');
        $this->addSql('ALTER TABLE repository_scan ADD provider_selection VARCHAR(128) DEFAULT NULL, DROP provider_id');
        $this->addSql('CREATE INDEX idx_repository_scan_provider ON repository_scan (provider_selection)');
    }
}
