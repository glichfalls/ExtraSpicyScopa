<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313152740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game (id INT AUTO_INCREMENT NOT NULL, chat_id BIGINT NOT NULL, status VARCHAR(255) NOT NULL, deck JSON NOT NULL, table_cards JSON NOT NULL, table_message_id BIGINT DEFAULT NULL, current_player_index SMALLINT NOT NULL, last_capture_player_index SMALLINT DEFAULT NULL, dealer_player_index SMALLINT NOT NULL, round_number SMALLINT NOT NULL, pending_card_ref VARCHAR(10) DEFAULT NULL, pending_captures JSON DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, player_index SMALLINT NOT NULL, hand JSON NOT NULL, captured_cards JSON NOT NULL, scope_count SMALLINT NOT NULL, score SMALLINT NOT NULL, game_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_98197A65E48FD905 (game_id), INDEX IDX_98197A65A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65E48FD905 FOREIGN KEY (game_id) REFERENCES game (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65E48FD905');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65A76ED395');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE player');
    }
}
