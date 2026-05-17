<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221152151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create voice_worker_session table';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE voice_worker_session (id VARCHAR(64) NOT NULL, pid INT NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_heartbeat_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        } else {
            $this->addSql('CREATE TABLE voice_worker_session (id VARCHAR(64) NOT NULL, pid INTEGER NOT NULL, started_at DATETIME NOT NULL, last_heartbeat_at DATETIME NOT NULL, PRIMARY KEY (id))');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE voice_worker_session');
    }
}
