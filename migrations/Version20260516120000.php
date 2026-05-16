<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add voice product MVP tables and extend voice_worker_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recording (id BLOB NOT NULL, session_id VARCHAR(64) NOT NULL, wav_path VARCHAR(1024) NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, status VARCHAR(32) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_RECORDING_SESSION FOREIGN KEY (session_id) REFERENCES voice_worker_session (id) ON DELETE CASCADE)');
        $this->addSql('CREATE INDEX IDX_RECORDING_SESSION ON recording (session_id)');

        $this->addSql('CREATE TABLE transcription (id BLOB NOT NULL, recording_id BLOB NOT NULL, provider VARCHAR(64) NOT NULL, language VARCHAR(16) DEFAULT NULL, full_text CLOB NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_TRANSCRIPTION_RECORDING FOREIGN KEY (recording_id) REFERENCES recording (id) ON DELETE CASCADE)');
        $this->addSql('CREATE INDEX IDX_TRANSCRIPTION_RECORDING ON transcription (recording_id)');
        $this->addSql('CREATE INDEX idx_transcription_full_text ON transcription (full_text)');

        $this->addSql('CREATE TABLE transcription_segment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, transcription_id BLOB NOT NULL, sequence INTEGER NOT NULL, text CLOB NOT NULL, is_final BOOLEAN NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_SEGMENT_TRANSCRIPTION FOREIGN KEY (transcription_id) REFERENCES transcription (id) ON DELETE CASCADE)');
        $this->addSql('CREATE INDEX idx_segment_transcription_sequence ON transcription_segment (transcription_id, sequence)');

        $this->addSql('ALTER TABLE voice_worker_session ADD COLUMN status VARCHAR(32) DEFAULT \'idle\' NOT NULL');
        $this->addSql('ALTER TABLE voice_worker_session ADD COLUMN label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE voice_worker_session ADD COLUMN provider VARCHAR(64) DEFAULT \'whisper_cpp\' NOT NULL');
        $this->addSql('ALTER TABLE voice_worker_session ADD COLUMN last_error CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE transcription_segment');
        $this->addSql('DROP TABLE transcription');
        $this->addSql('DROP TABLE recording');
        // SQLite does not support DROP COLUMN easily; recreate table in production if needed
    }
}
