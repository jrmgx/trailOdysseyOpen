<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241027122643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Broadcast related migration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE diary_entry ADD broadcast_identifiers JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD mastodon_info JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE diary_entry DROP broadcast_identifiers');
        $this->addSql('ALTER TABLE user DROP mastodon_info');
    }
}
