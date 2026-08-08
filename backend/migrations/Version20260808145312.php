<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260808145312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account verification and password reset/change token columns to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD is_verified TINYINT NOT NULL DEFAULT 0, ADD verification_token_hash VARCHAR(64) DEFAULT NULL, ADD verification_token_expires_at DATETIME DEFAULT NULL, ADD password_reset_token_hash VARCHAR(64) DEFAULT NULL, ADD password_reset_token_expires_at DATETIME DEFAULT NULL, ADD pending_password_hash VARCHAR(255) DEFAULT NULL, ADD password_change_token_hash VARCHAR(64) DEFAULT NULL, ADD password_change_token_expires_at DATETIME DEFAULT NULL');
        // Grandfather in accounts that existed before this feature shipped —
        // they never had a chance to verify an email, so don't lock them out.
        $this->addSql('UPDATE `user` SET is_verified = 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP is_verified, DROP verification_token_hash, DROP verification_token_expires_at, DROP password_reset_token_hash, DROP password_reset_token_expires_at, DROP pending_password_hash, DROP password_change_token_hash, DROP password_change_token_expires_at');
    }
}
