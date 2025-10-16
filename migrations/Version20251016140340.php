<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016140340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE scan_result (id INT AUTO_INCREMENT NOT NULL, upload_id INT NOT NULL, metadata JSON DEFAULT NULL, scanned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CFDBE4EDCCCFBA31 (upload_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE upload (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, repository_name VARCHAR(255) DEFAULT NULL, commit_name VARCHAR(255) DEFAULT NULL, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vulnerability (id INT AUTO_INCREMENT NOT NULL, scan_result_id INT NOT NULL, package_name VARCHAR(255) NOT NULL, severity VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_6C4E4047EC68BBB8 (scan_result_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE scan_result ADD CONSTRAINT FK_CFDBE4EDCCCFBA31 FOREIGN KEY (upload_id) REFERENCES upload (id)');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_6C4E4047EC68BBB8 FOREIGN KEY (scan_result_id) REFERENCES scan_result (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scan_result DROP FOREIGN KEY FK_CFDBE4EDCCCFBA31');
        $this->addSql('ALTER TABLE vulnerability DROP FOREIGN KEY FK_6C4E4047EC68BBB8');
        $this->addSql('DROP TABLE scan_result');
        $this->addSql('DROP TABLE upload');
        $this->addSql('DROP TABLE vulnerability');
    }
}
