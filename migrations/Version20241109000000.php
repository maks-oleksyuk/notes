<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241109000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add User entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user (
              id INT AUTO_INCREMENT NOT NULL,
              username VARCHAR(30) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) DEFAULT NULL,
              UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE user
        SQL);
    }
}
