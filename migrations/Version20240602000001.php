<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240602000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add option on Tiles to force proxy use';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tiles ADD use_proxy TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE tiles CHANGE use_proxy use_proxy TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tiles DROP use_proxy');
    }
}
