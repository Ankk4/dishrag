<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MVP schema: auth, chat, documents, chunks (pgvector), ingestion jobs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(255) NOT NULL, password_hash TEXT NOT NULL, name VARCHAR(120) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_email ON users (email)');

        $this->addSql('CREATE TABLE refresh_tokens (id UUID NOT NULL, user_id UUID NOT NULL, token_hash VARCHAR(128) NOT NULL, jti UUID NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, replaced_by_jti UUID DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_refresh_tokens_token_hash ON refresh_tokens (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_refresh_tokens_jti ON refresh_tokens (jti)');
        $this->addSql('CREATE INDEX IDX_refresh_tokens_user_id ON refresh_tokens (user_id)');
        $this->addSql('CREATE INDEX IDX_refresh_tokens_expires_at ON refresh_tokens (expires_at)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE chat_sessions (id UUID NOT NULL, user_id UUID NOT NULL, title VARCHAR(200) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_chat_sessions_user_created ON chat_sessions (user_id, created_at DESC)');
        $this->addSql('ALTER TABLE chat_sessions ADD CONSTRAINT FK_chat_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE chat_messages (id UUID NOT NULL, session_id UUID NOT NULL, role VARCHAR(20) NOT NULL, content TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'done', client_message_id VARCHAR(100) DEFAULT NULL, metadata JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IDX_chat_messages_session_created ON chat_messages (session_id, created_at ASC)');
        $this->addSql('CREATE UNIQUE INDEX uq_session_client_msg ON chat_messages (session_id, client_message_id)');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_chat_messages_session FOREIGN KEY (session_id) REFERENCES chat_sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE documents (id UUID NOT NULL, owner_user_id UUID DEFAULT NULL, source_type VARCHAR(30) NOT NULL, source_uri TEXT NOT NULL, doc_type VARCHAR(50) NOT NULL, title TEXT DEFAULT NULL, language VARCHAR(10) NOT NULL, content TEXT NOT NULL, metadata JSONB NOT NULL, content_sha256 CHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uq_documents_content_hash ON documents (content_sha256)');
        $this->addSql('CREATE INDEX IDX_documents_owner ON documents (owner_user_id)');
        $this->addSql('CREATE INDEX IDX_documents_source_type ON documents (source_type)');
        $this->addSql('CREATE INDEX IDX_documents_metadata_gin ON documents USING GIN (metadata)');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_documents_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE chunks (id UUID NOT NULL, document_id UUID NOT NULL, owner_user_id UUID DEFAULT NULL, chunk_index INT NOT NULL, text_content TEXT NOT NULL, token_count INT NOT NULL, embedding vector(1536) NOT NULL, metadata JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uq_document_chunk_idx ON chunks (document_id, chunk_index)');
        $this->addSql('CREATE INDEX IDX_chunks_document_id ON chunks (document_id)');
        $this->addSql('CREATE INDEX IDX_chunks_owner_user_id ON chunks (owner_user_id)');
        $this->addSql('CREATE INDEX IDX_chunks_metadata_gin ON chunks USING GIN (metadata)');
        $this->addSql('CREATE INDEX idx_chunks_embedding_hnsw ON chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 128)');
        $this->addSql('ALTER TABLE chunks ADD CONSTRAINT FK_chunks_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE chunks ADD CONSTRAINT FK_chunks_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE ingestion_jobs (id UUID NOT NULL, owner_user_id UUID NOT NULL, source_type VARCHAR(30) NOT NULL, source_uri TEXT NOT NULL, status VARCHAR(20) NOT NULL, external_key VARCHAR(120) DEFAULT NULL, stats JSONB NOT NULL, error TEXT DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uq_ingestion_jobs_external_key ON ingestion_jobs (external_key)');
        $this->addSql('CREATE INDEX IDX_ingestion_jobs_owner_created ON ingestion_jobs (owner_user_id, created_at DESC)');
        $this->addSql('CREATE INDEX IDX_ingestion_jobs_status ON ingestion_jobs (status)');
        $this->addSql('ALTER TABLE ingestion_jobs ADD CONSTRAINT FK_ingestion_jobs_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ingestion_jobs');
        $this->addSql('DROP TABLE chunks');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE chat_messages');
        $this->addSql('DROP TABLE chat_sessions');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE users');
    }
}
