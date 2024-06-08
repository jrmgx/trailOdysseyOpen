<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240608075438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nickname';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD nickname VARCHAR(80) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE `user` SET `nickname` = LEFT(MD5(RAND()), 8);');
        $this->addSql('ALTER TABLE user CHANGE nickname nickname VARCHAR(80) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A188FE64 ON user (nickname)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649A188FE64 ON user');
        $this->addSql('ALTER TABLE user DROP nickname');
    }
}
