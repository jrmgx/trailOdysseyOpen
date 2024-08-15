<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240815102442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove leavingAt from stage and related migration calculation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stage DROP leaving_at');
        // We also want to have every arrivingAt date to be at midnight UTC as we won't use time anymore
        $this->addSql('UPDATE stage SET arriving_at = DATE(arriving_at);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stage ADD leaving_at DATETIME NOT NULL DEFAULT "2024-08-15 00:00:00"');
    }
}
