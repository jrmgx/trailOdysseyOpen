<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version00000000000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'First migration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bag (
          id INT AUTO_INCREMENT NOT NULL,
          user_id INT NOT NULL,
          trip_id INT NOT NULL,
          parent_bag_id INT DEFAULT NULL,
          created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          name VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          weight INT DEFAULT NULL,
          checked TINYINT(1) NOT NULL,
          INDEX IDX_1B226841A76ED395 (user_id),
          INDEX IDX_1B226841A5BC2E0E (trip_id),
          INDEX IDX_1B226841204E4151 (parent_bag_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE diary_entry (
          id INT AUTO_INCREMENT NOT NULL,
          trip_id INT NOT NULL,
          user_id INT NOT NULL,
          type VARCHAR(16) DEFAULT NULL,
          symbol VARCHAR(16) DEFAULT NULL,
          name VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          arriving_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          point_name VARCHAR(255) NOT NULL,
          point_lat VARCHAR(255) NOT NULL,
          point_lon VARCHAR(255) NOT NULL,
          INDEX IDX_6A3E3D51A5BC2E0E (trip_id),
          INDEX IDX_6A3E3D51A76ED395 (user_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gear (
          id INT AUTO_INCREMENT NOT NULL,
          user_id INT NOT NULL,
          created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          name VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          weight INT DEFAULT NULL,
          INDEX IDX_B44539BA76ED395 (user_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gear_in_bag (
          id INT AUTO_INCREMENT NOT NULL,
          gear_id INT NOT NULL,
          bag_id INT NOT NULL,
          created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          count INT NOT NULL,
          checked TINYINT(1) NOT NULL,
          INDEX IDX_2BA25E2E77201934 (gear_id),
          INDEX IDX_2BA25E2E6F5D8297 (bag_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE interest (
          id INT AUTO_INCREMENT NOT NULL,
          trip_id INT NOT NULL,
          user_id INT NOT NULL,
          type VARCHAR(16) DEFAULT NULL,
          symbol VARCHAR(16) DEFAULT NULL,
          name VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          arriving_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          point_name VARCHAR(255) NOT NULL,
          point_lat VARCHAR(255) NOT NULL,
          point_lon VARCHAR(255) NOT NULL,
          INDEX IDX_6C3E1A67A5BC2E0E (trip_id),
          INDEX IDX_6C3E1A67A76ED395 (user_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE photo (
          id INT AUTO_INCREMENT NOT NULL,
          user_id INT NOT NULL,
          trip_id INT NOT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          path VARCHAR(255) NOT NULL,
          INDEX IDX_14B78418A76ED395 (user_id),
          INDEX IDX_14B78418A5BC2E0E (trip_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE routing (
          id INT AUTO_INCREMENT NOT NULL,
          user_id INT NOT NULL,
          trip_id INT NOT NULL,
          start_stage_id INT NOT NULL,
          finish_stage_id INT NOT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          distance INT DEFAULT NULL,
          as_the_crow_fly TINYINT(1) NOT NULL,
          path_points_store LONGTEXT DEFAULT NULL,
          elevation_positive INT DEFAULT NULL,
          elevation_negative INT DEFAULT NULL,
          INDEX IDX_A5F8B9FAA76ED395 (user_id),
          INDEX IDX_A5F8B9FAA5BC2E0E (trip_id),
          UNIQUE INDEX UNIQ_A5F8B9FA3DB297A1 (start_stage_id),
          UNIQUE INDEX UNIQ_A5F8B9FA9BD392C3 (finish_stage_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE segment (
          id INT AUTO_INCREMENT NOT NULL,
          user_id INT NOT NULL,
          trip_id INT NOT NULL,
          name VARCHAR(255) NOT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          points JSON NOT NULL,
          bounding_box JSON NOT NULL,
          INDEX IDX_1881F565A76ED395 (user_id),
          INDEX IDX_1881F565A5BC2E0E (trip_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stage (
          id INT AUTO_INCREMENT NOT NULL,
          trip_id INT NOT NULL,
          user_id INT NOT NULL,
          leaving_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          timezone VARCHAR(255) NOT NULL,
          name VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          arriving_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          point_name VARCHAR(255) NOT NULL,
          point_lat VARCHAR(255) NOT NULL,
          point_lon VARCHAR(255) NOT NULL,
          INDEX IDX_C27C9369A5BC2E0E (trip_id),
          INDEX IDX_C27C9369A76ED395 (user_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tiles (
          id INT AUTO_INCREMENT NOT NULL,
          trip_id INT NOT NULL,
          name VARCHAR(255) NOT NULL,
          url VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          overlay TINYINT(1) NOT NULL,
          public TINYINT(1) NOT NULL,
          geo_json TINYINT(1) NOT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          geo_json_html LONGTEXT DEFAULT NULL,
          position INT NOT NULL,
          INDEX IDX_1C1584BBA5BC2E0E (trip_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE trip (
          id INT AUTO_INCREMENT NOT NULL,
          user_id INT NOT NULL,
          name VARCHAR(255) NOT NULL,
          description LONGTEXT DEFAULT NULL,
          updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          share_key VARCHAR(255) DEFAULT NULL,
          map_zoom INT NOT NULL,
          progress_point_store JSON DEFAULT NULL,
          map_center_lat VARCHAR(255) NOT NULL,
          map_center_lon VARCHAR(255) NOT NULL,
          INDEX IDX_7656F53BA76ED395 (user_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (
          id INT AUTO_INCREMENT NOT NULL,
          username VARCHAR(180) NOT NULL,
          roles JSON NOT NULL,
          password VARCHAR(255) NOT NULL,
          timezone VARCHAR(32) DEFAULT \'UTC\' NOT NULL,
          UNIQUE INDEX UNIQ_8D93D649F85E0677 (username),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (
          id BIGINT AUTO_INCREMENT NOT NULL,
          body LONGTEXT NOT NULL,
          headers LONGTEXT NOT NULL,
          queue_name VARCHAR(190) NOT NULL,
          created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
          INDEX IDX_75EA56E0FB7336F0 (queue_name),
          INDEX IDX_75EA56E0E3BD61CE (available_at),
          INDEX IDX_75EA56E016BA31DB (delivered_at),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bag ADD CONSTRAINT FK_1B226841A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE bag ADD CONSTRAINT FK_1B226841A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE bag ADD CONSTRAINT FK_1B226841204E4151 FOREIGN KEY (parent_bag_id) REFERENCES bag (id)');
        $this->addSql('ALTER TABLE diary_entry ADD CONSTRAINT FK_6A3E3D51A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE diary_entry ADD CONSTRAINT FK_6A3E3D51A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE gear ADD CONSTRAINT FK_B44539BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE gear_in_bag ADD CONSTRAINT FK_2BA25E2E77201934 FOREIGN KEY (gear_id) REFERENCES gear (id)');
        $this->addSql('ALTER TABLE gear_in_bag ADD CONSTRAINT FK_2BA25E2E6F5D8297 FOREIGN KEY (bag_id) REFERENCES bag (id)');
        $this->addSql('ALTER TABLE interest ADD CONSTRAINT FK_6C3E1A67A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE interest ADD CONSTRAINT FK_6C3E1A67A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE routing ADD CONSTRAINT FK_A5F8B9FAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE routing ADD CONSTRAINT FK_A5F8B9FAA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE routing ADD CONSTRAINT FK_A5F8B9FA3DB297A1 FOREIGN KEY (start_stage_id) REFERENCES stage (id)');
        $this->addSql('ALTER TABLE routing ADD CONSTRAINT FK_A5F8B9FA9BD392C3 FOREIGN KEY (finish_stage_id) REFERENCES stage (id)');
        $this->addSql('ALTER TABLE segment ADD CONSTRAINT FK_1881F565A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE segment ADD CONSTRAINT FK_1881F565A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE stage ADD CONSTRAINT FK_C27C9369A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE stage ADD CONSTRAINT FK_C27C9369A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tiles ADD CONSTRAINT FK_1C1584BBA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id)');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
    }
}
