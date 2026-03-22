<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize interest.arriving_at to midnight (date-only, same as stage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE interest SET arriving_at = date_trunc(\'day\', arriving_at)');
    }

    public function down(Schema $schema): void
    {
    }
}
