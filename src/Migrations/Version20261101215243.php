<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101215243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes : activation par invitation, liens à usage unique ; prêts : date du dernier rappel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_account_token (id UUID NOT NULL, purpose VARCHAR(20) NOT NULL, secret_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F3C545E6A76ED395 ON app_account_token (user_id)');
        $this->addSql('ALTER TABLE app_account_token ADD CONSTRAINT FK_F3C545E6A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE app_user ADD activated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Les comptes existants ont déjà un mot de passe : ils sont considérés comme activés
        $this->addSql("UPDATE app_user SET activated_at = created_at WHERE password <> ''");
        $this->addSql('ALTER TABLE lending_loan ADD last_reminder_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_account_token DROP CONSTRAINT FK_F3C545E6A76ED395');
        $this->addSql('DROP TABLE app_account_token');
        $this->addSql('ALTER TABLE app_user DROP activated_at');
        $this->addSql('ALTER TABLE lending_loan DROP last_reminder_at');
    }
}
