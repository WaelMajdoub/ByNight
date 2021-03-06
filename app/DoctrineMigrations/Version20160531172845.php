<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160531172845 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE BlackList');
        $this->addSql('ALTER TABLE User ADD show_socials TINYINT(1) DEFAULT 1, ADD website VARCHAR(255) DEFAULT NULL');

        $this->addSql('UPDATE User SET from_login = 1 WHERE CHAR_LENGTH(password) > 25');
        $this->addSql('UPDATE User SET from_login = 0 WHERE CHAR_LENGTH(password) <= 25');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE BlackList (id INT AUTO_INCREMENT NOT NULL, site_id INT NOT NULL, facebook_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, reason VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, INDEX IDX_19D47E18F6BD1646 (site_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE BlackList ADD CONSTRAINT FK_19D47E18F6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('ALTER TABLE User DROP show_socials, DROP website');
    }
}
