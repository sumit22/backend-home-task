<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029084937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE integration DROP FOREIGN KEY FK_FDE96D9BA53A8AA');
        $this->addSql('DROP INDEX idx_integration_provider ON integration');
        $this->addSql('ALTER TABLE integration ADD provider_code VARCHAR(64) NOT NULL, DROP provider_id');
        $this->addSql('CREATE INDEX idx_integration_provider ON integration (provider_code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_integration_provider ON integration');
        $this->addSql('ALTER TABLE integration ADD provider_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', DROP provider_code');
        $this->addSql('ALTER TABLE integration ADD CONSTRAINT FK_FDE96D9BA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_integration_provider ON integration (provider_id)');
    }
}
