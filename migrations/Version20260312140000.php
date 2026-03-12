<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_admin and password columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD is_admin TINYINT(1) DEFAULT 0 NOT NULL, ADD password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP is_admin, DROP password');
    }
}
