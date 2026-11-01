<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101200506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventaire : emplacements, catégories, équipements, journal et séquence des codes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE inventory_asset_code_seq START WITH 1 INCREMENT BY 1');
        $this->addSql('CREATE TABLE inventory_asset (id UUID NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, brand VARCHAR(80) DEFAULT NULL, model VARCHAR(80) DEFAULT NULL, serial_number VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, physical_condition VARCHAR(16) NOT NULL, purchased_at DATE DEFAULT NULL, purchase_price NUMERIC(12, 2) DEFAULT NULL, warranty_ends_at DATE DEFAULT NULL, supplier VARCHAR(120) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, category_id UUID NOT NULL, location_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DE33F15C77153098 ON inventory_asset (code)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DE33F15CD948EE2 ON inventory_asset (serial_number)');
        $this->addSql('CREATE INDEX idx_asset_status ON inventory_asset (status)');
        $this->addSql('CREATE INDEX IDX_DE33F15C12469DE2 ON inventory_asset (category_id)');
        $this->addSql('CREATE INDEX IDX_DE33F15C64D218E ON inventory_asset (location_id)');
        $this->addSql('CREATE TABLE inventory_asset_event (id UUID NOT NULL, type VARCHAR(20) NOT NULL, summary VARCHAR(255) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, asset_id UUID NOT NULL, actor_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_asset_event_occurred ON inventory_asset_event (asset_id, occurred_at)');
        $this->addSql('CREATE INDEX IDX_DA07182F5DA1941 ON inventory_asset_event (asset_id)');
        $this->addSql('CREATE INDEX IDX_DA07182F10DAF24A ON inventory_asset_event (actor_id)');
        $this->addSql('CREATE TABLE inventory_category (id UUID NOT NULL, name VARCHAR(80) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B206A3505E237E06 ON inventory_category (name)');
        $this->addSql('CREATE TABLE inventory_location (id UUID NOT NULL, name VARCHAR(120) NOT NULL, kind VARCHAR(16) NOT NULL, parent_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EAD4335A727ACA70 ON inventory_location (parent_id)');
        $this->addSql('ALTER TABLE inventory_asset ADD CONSTRAINT FK_DE33F15C12469DE2 FOREIGN KEY (category_id) REFERENCES inventory_category (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE inventory_asset ADD CONSTRAINT FK_DE33F15C64D218E FOREIGN KEY (location_id) REFERENCES inventory_location (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE inventory_asset_event ADD CONSTRAINT FK_DA07182F5DA1941 FOREIGN KEY (asset_id) REFERENCES inventory_asset (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE inventory_asset_event ADD CONSTRAINT FK_DA07182F10DAF24A FOREIGN KEY (actor_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE inventory_location ADD CONSTRAINT FK_EAD4335A727ACA70 FOREIGN KEY (parent_id) REFERENCES inventory_location (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE inventory_asset_code_seq');
        $this->addSql('ALTER TABLE inventory_asset DROP CONSTRAINT FK_DE33F15C12469DE2');
        $this->addSql('ALTER TABLE inventory_asset DROP CONSTRAINT FK_DE33F15C64D218E');
        $this->addSql('ALTER TABLE inventory_asset_event DROP CONSTRAINT FK_DA07182F5DA1941');
        $this->addSql('ALTER TABLE inventory_asset_event DROP CONSTRAINT FK_DA07182F10DAF24A');
        $this->addSql('ALTER TABLE inventory_location DROP CONSTRAINT FK_EAD4335A727ACA70');
        $this->addSql('DROP TABLE inventory_asset');
        $this->addSql('DROP TABLE inventory_asset_event');
        $this->addSql('DROP TABLE inventory_category');
        $this->addSql('DROP TABLE inventory_location');
    }
}
