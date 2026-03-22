<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250322120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add interest.checkpoint for routing via-points';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interest ADD checkpoint BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interest DROP checkpoint');
    }
}
