<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313145523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card (id INT AUTO_INCREMENT NOT NULL, suit VARCHAR(255) NOT NULL, value SMALLINT NOT NULL, telegram_file_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX card_suit_value (suit, value), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, telegram_id BIGINT NOT NULL, username VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) NOT NULL, daily_limit INT DEFAULT 5 NOT NULL, banned TINYINT DEFAULT 0 NOT NULL, is_admin TINYINT DEFAULT 0 NOT NULL, password VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649CC0B3066 (telegram_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE card');
        $this->addSql('DROP TABLE `user`');
    }
}
