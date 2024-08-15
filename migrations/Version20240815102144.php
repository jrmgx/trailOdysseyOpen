<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240815102144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Database schema update related to composer package update';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bag CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE diary_entry CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE arriving_at arriving_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE gear CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE gear_in_bag CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE interest CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE arriving_at arriving_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE photo CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE routing CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE segment CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE stage CHANGE leaving_at leaving_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE arriving_at arriving_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE tiles CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE trip CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
