<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260807233021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE diary_entry (id INT AUTO_INCREMENT NOT NULL, quantity DOUBLE PRECISION NOT NULL, unit VARCHAR(10) NOT NULL, meal_type VARCHAR(20) NOT NULL, consumed_at DATE NOT NULL, kcal_at_logging DOUBLE PRECISION NOT NULL, protein_at_logging DOUBLE PRECISION NOT NULL, carbs_at_logging DOUBLE PRECISION NOT NULL, fat_at_logging DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, food_id INT NOT NULL, INDEX IDX_6A3E3D51A76ED395 (user_id), INDEX IDX_6A3E3D51BA8E87C4 (food_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE food (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, source VARCHAR(20) NOT NULL, barcode VARCHAR(64) DEFAULT NULL, off_id VARCHAR(64) DEFAULT NULL, kcal_per100 DOUBLE PRECISION NOT NULL, protein_per100 DOUBLE PRECISION NOT NULL, carbs_per100 DOUBLE PRECISION NOT NULL, fat_per100 DOUBLE PRECISION NOT NULL, fiber_per100 DOUBLE PRECISION DEFAULT NULL, default_unit VARCHAR(10) NOT NULL, unit_weight_grams DOUBLE PRECISION DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_user_id INT DEFAULT NULL, INDEX IDX_D43829F72B18554A (owner_user_id), UNIQUE INDEX uniq_source_off_id (source, off_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, daily_calorie_goal INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE diary_entry ADD CONSTRAINT FK_6A3E3D51A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE diary_entry ADD CONSTRAINT FK_6A3E3D51BA8E87C4 FOREIGN KEY (food_id) REFERENCES food (id)');
        $this->addSql('ALTER TABLE food ADD CONSTRAINT FK_D43829F72B18554A FOREIGN KEY (owner_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE diary_entry DROP FOREIGN KEY FK_6A3E3D51A76ED395');
        $this->addSql('ALTER TABLE diary_entry DROP FOREIGN KEY FK_6A3E3D51BA8E87C4');
        $this->addSql('ALTER TABLE food DROP FOREIGN KEY FK_D43829F72B18554A');
        $this->addSql('DROP TABLE diary_entry');
        $this->addSql('DROP TABLE food');
        $this->addSql('DROP TABLE `user`');
    }
}
