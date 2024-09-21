<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240921164234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add export filename pattern';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD export_filename_pattern VARCHAR(64) DEFAULT \'{counter}{stage_name}{trip_name}\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP export_filename_pattern');
    }
}
