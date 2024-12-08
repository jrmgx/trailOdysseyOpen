<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241207102633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is calculating segement on trip';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trip ADD is_calculating_segment TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trip DROP is_calculating_segment');
    }
}
