<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101203733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prêts : prêts, lignes de prêt (un seul prêt ouvert par équipement) et séquence des références';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE lending_loan_reference_seq START WITH 1 INCREMENT BY 1');
        $this->addSql('CREATE TABLE lending_loan (id UUID NOT NULL, reference VARCHAR(20) NOT NULL, borrower_name VARCHAR(160) NOT NULL, borrower_group VARCHAR(80) DEFAULT NULL, purpose VARCHAR(160) DEFAULT NULL, lent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, due_at DATE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, borrower_account_id UUID DEFAULT NULL, lent_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E6A70038AEA34913 ON lending_loan (reference)');
        $this->addSql('CREATE INDEX idx_loan_open_due ON lending_loan (closed_at, due_at)');
        $this->addSql('CREATE INDEX IDX_E6A70038EDD406A7 ON lending_loan (borrower_account_id)');
        $this->addSql('CREATE INDEX IDX_E6A7003872FE63E9 ON lending_loan (lent_by_id)');
        $this->addSql('CREATE TABLE lending_loan_line (id UUID NOT NULL, condition_out VARCHAR(16) NOT NULL, returned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, condition_in VARCHAR(16) DEFAULT NULL, loan_id UUID NOT NULL, asset_id UUID NOT NULL, received_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_loan_line_open_asset ON lending_loan_line (asset_id) WHERE returned_at IS NULL');
        $this->addSql('CREATE INDEX IDX_C9732825CE73868F ON lending_loan_line (loan_id)');
        $this->addSql('CREATE INDEX IDX_C97328255DA1941 ON lending_loan_line (asset_id)');
        $this->addSql('CREATE INDEX IDX_C97328256F8DDD17 ON lending_loan_line (received_by_id)');
        $this->addSql('ALTER TABLE lending_loan ADD CONSTRAINT FK_E6A70038EDD406A7 FOREIGN KEY (borrower_account_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lending_loan ADD CONSTRAINT FK_E6A7003872FE63E9 FOREIGN KEY (lent_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lending_loan_line ADD CONSTRAINT FK_C9732825CE73868F FOREIGN KEY (loan_id) REFERENCES lending_loan (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lending_loan_line ADD CONSTRAINT FK_C97328255DA1941 FOREIGN KEY (asset_id) REFERENCES inventory_asset (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lending_loan_line ADD CONSTRAINT FK_C97328256F8DDD17 FOREIGN KEY (received_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE lending_loan_reference_seq');
        $this->addSql('ALTER TABLE lending_loan DROP CONSTRAINT FK_E6A70038EDD406A7');
        $this->addSql('ALTER TABLE lending_loan DROP CONSTRAINT FK_E6A7003872FE63E9');
        $this->addSql('ALTER TABLE lending_loan_line DROP CONSTRAINT FK_C9732825CE73868F');
        $this->addSql('ALTER TABLE lending_loan_line DROP CONSTRAINT FK_C97328255DA1941');
        $this->addSql('ALTER TABLE lending_loan_line DROP CONSTRAINT FK_C97328256F8DDD17');
        $this->addSql('DROP TABLE lending_loan');
        $this->addSql('DROP TABLE lending_loan_line');
    }
}
