<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241112191620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add distance to Segments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE segment ADD distance INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE segment DROP distance');
    }
}
