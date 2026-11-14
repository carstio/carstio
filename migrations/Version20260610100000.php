<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consolidated initial Carstio schema.
 *
 * Squashes the previous migration history (initial schema + odometer
 * columns) into a single migration. The application has not been
 * deployed yet, so no shared database depends on the older versions.
 *
 * Carstio supports two database families, selected through DATABASE_URL
 * alone. Each migration in this directory therefore carries one branch
 * per platform (MariaDB / MySQL and PostgreSQL). The SQL of both branches
 * is generated from the same Doctrine mapping, so `doctrine:schema:validate`
 * must stay green on both engines.
 */
final class Version20260610100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial Carstio schema: users, settings, brands/models, clients, cars, companies, parts, offers, work orders (with odometer reading + unit), payments, attachments.';
    }

    public function up(Schema $schema): void
    {
        if ($this->platform instanceof PostgreSQLPlatform) {
            $this->upPostgreSql();

            return;
        }

        $this->upMariaDb();
    }

    public function down(Schema $schema): void
    {
        if ($this->platform instanceof PostgreSQLPlatform) {
            $this->downPostgreSql();

            return;
        }

        $this->downMariaDb();
    }

    private function upMariaDb(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) NOT NULL,
              full_name VARCHAR(255) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              preferred_locale VARCHAR(5) DEFAULT NULL,
              UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE attachment (
              id INT AUTO_INCREMENT NOT NULL,
              owner_type VARCHAR(32) NOT NULL,
              owner_id INT NOT NULL,
              category VARCHAR(32) NOT NULL,
              original_filename VARCHAR(255) NOT NULL,
              stored_filename VARCHAR(128) NOT NULL,
              storage_path VARCHAR(512) NOT NULL,
              mime_type VARCHAR(128) NOT NULL,
              size INT NOT NULL,
              extension VARCHAR(16) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              uploaded_by_id INT DEFAULT NULL,
              INDEX IDX_795FD9BBA2B28FE8 (uploaded_by_id),
              INDEX idx_attachment_owner (owner_type, owner_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE brand (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE car (
              id INT AUTO_INCREMENT NOT NULL,
              vin VARCHAR(255) DEFAULT NULL,
              license_plate VARCHAR(255) DEFAULT NULL,
              client_id INT NOT NULL,
              model_id INT NOT NULL,
              INDEX IDX_773DE69D19EB6921 (client_id),
              INDEX IDX_773DE69D7975B7E7 (model_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE client (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              phone VARCHAR(255) NOT NULL,
              email VARCHAR(255) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              city VARCHAR(255) NOT NULL,
              address VARCHAR(255) NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              vat_identifier VARCHAR(255) DEFAULT NULL,
              vat TINYINT NOT NULL,
              client_id INT NOT NULL,
              INDEX IDX_4FBF094F19EB6921 (client_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE model (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              brand_id INT NOT NULL,
              INDEX IDX_D79572D944F5D008 (brand_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE offer (
              id INT AUTO_INCREMENT NOT NULL,
              number VARCHAR(50) NOT NULL,
              status VARCHAR(30) NOT NULL,
              title VARCHAR(255) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              valid_until DATETIME DEFAULT NULL,
              subtotal_labor NUMERIC(10, 2) NOT NULL,
              subtotal_parts NUMERIC(10, 2) NOT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_amount NUMERIC(10, 2) NOT NULL,
              total_amount NUMERIC(10, 2) NOT NULL,
              currency_code VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              converted_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              client_id INT NOT NULL,
              car_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              work_order_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_29D6873E96901F54 (number),
              INDEX IDX_29D6873E19EB6921 (client_id),
              INDEX IDX_29D6873EC3C6F69F (car_id),
              INDEX IDX_29D6873EB03A8386 (created_by_id),
              INDEX IDX_29D6873E582AE764 (work_order_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE offer_item (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(20) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price NUMERIC(10, 2) NOT NULL,
              cost_price NUMERIC(10, 2) DEFAULT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_rate NUMERIC(5, 2) NOT NULL,
              line_total NUMERIC(10, 2) NOT NULL,
              sort_order INT NOT NULL,
              created_at DATETIME NOT NULL,
              offer_id INT NOT NULL,
              part_id INT DEFAULT NULL,
              INDEX IDX_E1E30B0953C674EE (offer_id),
              INDEX IDX_E1E30B094CE34BEC (part_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE part (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              sku VARCHAR(100) DEFAULT NULL,
              part_number VARCHAR(100) DEFAULT NULL,
              brand VARCHAR(100) DEFAULT NULL,
              cost_price NUMERIC(10, 2) DEFAULT NULL,
              sell_price NUMERIC(10, 2) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              is_active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment (
              id INT AUTO_INCREMENT NOT NULL,
              amount NUMERIC(10, 2) NOT NULL,
              method VARCHAR(30) NOT NULL,
              status VARCHAR(30) NOT NULL,
              paid_at DATETIME NOT NULL,
              reference VARCHAR(255) DEFAULT NULL,
              note LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              work_order_id INT NOT NULL,
              INDEX IDX_6D28840D582AE764 (work_order_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE setting (
              id INT AUTO_INCREMENT NOT NULL,
              section VARCHAR(64) NOT NULL,
              setting_key VARCHAR(128) NOT NULL,
              value LONGTEXT DEFAULT NULL,
              type VARCHAR(32) NOT NULL,
              sort_order SMALLINT DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_setting_section (section),
              UNIQUE INDEX uniq_setting_section_key (section, setting_key),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE work_order (
              id INT AUTO_INCREMENT NOT NULL,
              number VARCHAR(50) NOT NULL,
              status VARCHAR(30) NOT NULL,
              source VARCHAR(100) DEFAULT NULL,
              complaint LONGTEXT NOT NULL,
              diagnosis LONGTEXT DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              subtotal_labor NUMERIC(10, 2) NOT NULL,
              subtotal_parts NUMERIC(10, 2) NOT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_amount NUMERIC(10, 2) NOT NULL,
              total_amount NUMERIC(10, 2) NOT NULL,
              currency_code VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              paid_amount NUMERIC(10, 2) NOT NULL,
              payment_status VARCHAR(30) NOT NULL,
              odometer_reading INT UNSIGNED DEFAULT NULL,
              odometer_unit VARCHAR(8) DEFAULT NULL,
              opened_at DATETIME NOT NULL,
              scheduled_at DATETIME DEFAULT NULL,
              started_at DATETIME DEFAULT NULL,
              completed_at DATETIME DEFAULT NULL,
              closed_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              client_id INT NOT NULL,
              car_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              UNIQUE INDEX UNIQ_DDD2E8B796901F54 (number),
              INDEX IDX_DDD2E8B719EB6921 (client_id),
              INDEX IDX_DDD2E8B7C3C6F69F (car_id),
              INDEX IDX_DDD2E8B7B03A8386 (created_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE work_order_item (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(20) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price NUMERIC(10, 2) NOT NULL,
              cost_price NUMERIC(10, 2) DEFAULT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_rate NUMERIC(5, 2) NOT NULL,
              line_total NUMERIC(10, 2) NOT NULL,
              sort_order INT NOT NULL,
              created_at DATETIME NOT NULL,
              work_order_id INT NOT NULL,
              part_id INT DEFAULT NULL,
              INDEX IDX_4989B2DA582AE764 (work_order_id),
              INDEX IDX_4989B2DA4CE34BEC (part_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
              id BIGINT AUTO_INCREMENT NOT NULL,
              body LONGTEXT NOT NULL,
              headers LONGTEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at DATETIME NOT NULL,
              available_at DATETIME NOT NULL,
              delivered_at DATETIME DEFAULT NULL,
              INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (
                queue_name, available_at, delivered_at,
                id
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              attachment
            ADD
              CONSTRAINT FK_795FD9BBA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              car
            ADD
              CONSTRAINT FK_773DE69D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              car
            ADD
              CONSTRAINT FK_773DE69D7975B7E7 FOREIGN KEY (model_id) REFERENCES model (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              company
            ADD
              CONSTRAINT FK_4FBF094F19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              model
            ADD
              CONSTRAINT FK_D79572D944F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              offer
            ADD
              CONSTRAINT FK_29D6873E19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873EC3C6F69F FOREIGN KEY (car_id) REFERENCES car (id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              offer
            ADD
              CONSTRAINT FK_29D6873EB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              offer
            ADD
              CONSTRAINT FK_29D6873E582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              offer_item
            ADD
              CONSTRAINT FK_E1E30B0953C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              offer_item
            ADD
              CONSTRAINT FK_E1E30B094CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              payment
            ADD
              CONSTRAINT FK_6D28840D582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_order
            ADD
              CONSTRAINT FK_DDD2E8B719EB6921 FOREIGN KEY (client_id) REFERENCES client (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_order
            ADD
              CONSTRAINT FK_DDD2E8B7C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_order
            ADD
              CONSTRAINT FK_DDD2E8B7B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_order_item
            ADD
              CONSTRAINT FK_4989B2DA582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_order_item
            ADD
              CONSTRAINT FK_4989B2DA4CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)
        SQL);
    }

    private function downMariaDb(): void
    {
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BBA2B28FE8');
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D19EB6921');
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D7975B7E7');
        $this->addSql('ALTER TABLE company DROP FOREIGN KEY FK_4FBF094F19EB6921');
        $this->addSql('ALTER TABLE model DROP FOREIGN KEY FK_D79572D944F5D008');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E19EB6921');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873EC3C6F69F');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873EB03A8386');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E582AE764');
        $this->addSql('ALTER TABLE offer_item DROP FOREIGN KEY FK_E1E30B0953C674EE');
        $this->addSql('ALTER TABLE offer_item DROP FOREIGN KEY FK_E1E30B094CE34BEC');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D582AE764');
        $this->addSql('ALTER TABLE work_order DROP FOREIGN KEY FK_DDD2E8B719EB6921');
        $this->addSql('ALTER TABLE work_order DROP FOREIGN KEY FK_DDD2E8B7C3C6F69F');
        $this->addSql('ALTER TABLE work_order DROP FOREIGN KEY FK_DDD2E8B7B03A8386');
        $this->addSql('ALTER TABLE work_order_item DROP FOREIGN KEY FK_4989B2DA582AE764');
        $this->addSql('ALTER TABLE work_order_item DROP FOREIGN KEY FK_4989B2DA4CE34BEC');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE car');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE model');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE offer_item');
        $this->addSql('DROP TABLE part');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE work_order');
        $this->addSql('DROP TABLE work_order_item');
        $this->addSql('DROP TABLE messenger_messages');
    }

    private function upPostgreSql(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              email VARCHAR(180) NOT NULL,
              full_name VARCHAR(255) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              is_active BOOLEAN DEFAULT true NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              preferred_locale VARCHAR(5) DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql(<<<'SQL'
            CREATE TABLE attachment (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              owner_type VARCHAR(32) NOT NULL,
              owner_id INT NOT NULL,
              category VARCHAR(32) NOT NULL,
              original_filename VARCHAR(255) NOT NULL,
              stored_filename VARCHAR(128) NOT NULL,
              storage_path VARCHAR(512) NOT NULL,
              mime_type VARCHAR(128) NOT NULL,
              size INT NOT NULL,
              extension VARCHAR(16) DEFAULT NULL,
              description TEXT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              uploaded_by_id INT DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_795FD9BBA2B28FE8 ON attachment (uploaded_by_id)');
        $this->addSql('CREATE INDEX idx_attachment_owner ON attachment (owner_type, owner_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE brand (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              name VARCHAR(255) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE car (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              vin VARCHAR(255) DEFAULT NULL,
              license_plate VARCHAR(255) DEFAULT NULL,
              client_id INT NOT NULL,
              model_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_773DE69D19EB6921 ON car (client_id)');
        $this->addSql('CREATE INDEX IDX_773DE69D7975B7E7 ON car (model_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE client (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              name VARCHAR(255) NOT NULL,
              phone VARCHAR(255) NOT NULL,
              email VARCHAR(255) DEFAULT NULL,
              notes TEXT DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE company (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              name VARCHAR(255) NOT NULL,
              city VARCHAR(255) NOT NULL,
              address VARCHAR(255) NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              vat_identifier VARCHAR(255) DEFAULT NULL,
              vat BOOLEAN NOT NULL,
              client_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_4FBF094F19EB6921 ON company (client_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE model (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              name VARCHAR(255) NOT NULL,
              brand_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_D79572D944F5D008 ON model (brand_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE offer (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              number VARCHAR(50) NOT NULL,
              status VARCHAR(30) NOT NULL,
              title VARCHAR(255) DEFAULT NULL,
              notes TEXT DEFAULT NULL,
              valid_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              subtotal_labor NUMERIC(10, 2) NOT NULL,
              subtotal_parts NUMERIC(10, 2) NOT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_amount NUMERIC(10, 2) NOT NULL,
              total_amount NUMERIC(10, 2) NOT NULL,
              currency_code VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              converted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              client_id INT NOT NULL,
              car_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              work_order_id INT DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_29D6873E96901F54 ON offer (number)');
        $this->addSql('CREATE INDEX IDX_29D6873E19EB6921 ON offer (client_id)');
        $this->addSql('CREATE INDEX IDX_29D6873EC3C6F69F ON offer (car_id)');
        $this->addSql('CREATE INDEX IDX_29D6873EB03A8386 ON offer (created_by_id)');
        $this->addSql('CREATE INDEX IDX_29D6873E582AE764 ON offer (work_order_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE offer_item (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              type VARCHAR(20) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price NUMERIC(10, 2) NOT NULL,
              cost_price NUMERIC(10, 2) DEFAULT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_rate NUMERIC(5, 2) NOT NULL,
              line_total NUMERIC(10, 2) NOT NULL,
              sort_order INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              offer_id INT NOT NULL,
              part_id INT DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_E1E30B0953C674EE ON offer_item (offer_id)');
        $this->addSql('CREATE INDEX IDX_E1E30B094CE34BEC ON offer_item (part_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE part (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              name VARCHAR(255) NOT NULL,
              sku VARCHAR(100) DEFAULT NULL,
              part_number VARCHAR(100) DEFAULT NULL,
              brand VARCHAR(100) DEFAULT NULL,
              cost_price NUMERIC(10, 2) DEFAULT NULL,
              sell_price NUMERIC(10, 2) DEFAULT NULL,
              notes TEXT DEFAULT NULL,
              is_active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE payment (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              amount NUMERIC(10, 2) NOT NULL,
              method VARCHAR(30) NOT NULL,
              status VARCHAR(30) NOT NULL,
              paid_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              reference VARCHAR(255) DEFAULT NULL,
              note TEXT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              work_order_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_6D28840D582AE764 ON payment (work_order_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE setting (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              section VARCHAR(64) NOT NULL,
              setting_key VARCHAR(128) NOT NULL,
              value TEXT DEFAULT NULL,
              type VARCHAR(32) NOT NULL,
              sort_order SMALLINT DEFAULT 0 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_setting_section ON setting (section)');
        $this->addSql('CREATE UNIQUE INDEX uniq_setting_section_key ON setting (section, setting_key)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_order (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              number VARCHAR(50) NOT NULL,
              status VARCHAR(30) NOT NULL,
              source VARCHAR(100) DEFAULT NULL,
              complaint TEXT NOT NULL,
              diagnosis TEXT DEFAULT NULL,
              notes TEXT DEFAULT NULL,
              subtotal_labor NUMERIC(10, 2) NOT NULL,
              subtotal_parts NUMERIC(10, 2) NOT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_amount NUMERIC(10, 2) NOT NULL,
              total_amount NUMERIC(10, 2) NOT NULL,
              currency_code VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              paid_amount NUMERIC(10, 2) NOT NULL,
              payment_status VARCHAR(30) NOT NULL,
              odometer_reading INT DEFAULT NULL,
              odometer_unit VARCHAR(8) DEFAULT NULL,
              opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              client_id INT NOT NULL,
              car_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DDD2E8B796901F54 ON work_order (number)');
        $this->addSql('CREATE INDEX IDX_DDD2E8B719EB6921 ON work_order (client_id)');
        $this->addSql('CREATE INDEX IDX_DDD2E8B7C3C6F69F ON work_order (car_id)');
        $this->addSql('CREATE INDEX IDX_DDD2E8B7B03A8386 ON work_order (created_by_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE work_order_item (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              type VARCHAR(20) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT DEFAULT NULL,
              quantity NUMERIC(10, 2) NOT NULL,
              unit_price NUMERIC(10, 2) NOT NULL,
              cost_price NUMERIC(10, 2) DEFAULT NULL,
              discount_amount NUMERIC(10, 2) NOT NULL,
              tax_rate NUMERIC(5, 2) NOT NULL,
              line_total NUMERIC(10, 2) NOT NULL,
              sort_order INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              work_order_id INT NOT NULL,
              part_id INT DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_4989B2DA582AE764 ON work_order_item (work_order_id)');
        $this->addSql('CREATE INDEX IDX_4989B2DA4CE34BEC ON work_order_item (part_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
              id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              body TEXT NOT NULL,
              headers TEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BBA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D7975B7E7 FOREIGN KEY (model_id) REFERENCES model (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE company ADD CONSTRAINT FK_4FBF094F19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE model ADD CONSTRAINT FK_D79572D944F5D008 FOREIGN KEY (brand_id) REFERENCES brand (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873EC3C6F69F FOREIGN KEY (car_id) REFERENCES car (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873EB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE offer_item ADD CONSTRAINT FK_E1E30B0953C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE offer_item ADD CONSTRAINT FK_E1E30B094CE34BEC FOREIGN KEY (part_id) REFERENCES part (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B719EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B7C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order ADD CONSTRAINT FK_DDD2E8B7B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order_item ADD CONSTRAINT FK_4989B2DA582AE764 FOREIGN KEY (work_order_id) REFERENCES work_order (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE work_order_item ADD CONSTRAINT FK_4989B2DA4CE34BEC FOREIGN KEY (part_id) REFERENCES part (id) NOT DEFERRABLE');
    }

    private function downPostgreSql(): void
    {
        $this->addSql('ALTER TABLE attachment DROP CONSTRAINT FK_795FD9BBA2B28FE8');
        $this->addSql('ALTER TABLE car DROP CONSTRAINT FK_773DE69D19EB6921');
        $this->addSql('ALTER TABLE car DROP CONSTRAINT FK_773DE69D7975B7E7');
        $this->addSql('ALTER TABLE company DROP CONSTRAINT FK_4FBF094F19EB6921');
        $this->addSql('ALTER TABLE model DROP CONSTRAINT FK_D79572D944F5D008');
        $this->addSql('ALTER TABLE offer DROP CONSTRAINT FK_29D6873E19EB6921');
        $this->addSql('ALTER TABLE offer DROP CONSTRAINT FK_29D6873EC3C6F69F');
        $this->addSql('ALTER TABLE offer DROP CONSTRAINT FK_29D6873EB03A8386');
        $this->addSql('ALTER TABLE offer DROP CONSTRAINT FK_29D6873E582AE764');
        $this->addSql('ALTER TABLE offer_item DROP CONSTRAINT FK_E1E30B0953C674EE');
        $this->addSql('ALTER TABLE offer_item DROP CONSTRAINT FK_E1E30B094CE34BEC');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D582AE764');
        $this->addSql('ALTER TABLE work_order DROP CONSTRAINT FK_DDD2E8B719EB6921');
        $this->addSql('ALTER TABLE work_order DROP CONSTRAINT FK_DDD2E8B7C3C6F69F');
        $this->addSql('ALTER TABLE work_order DROP CONSTRAINT FK_DDD2E8B7B03A8386');
        $this->addSql('ALTER TABLE work_order_item DROP CONSTRAINT FK_4989B2DA582AE764');
        $this->addSql('ALTER TABLE work_order_item DROP CONSTRAINT FK_4989B2DA4CE34BEC');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE car');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE model');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE offer_item');
        $this->addSql('DROP TABLE part');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE work_order');
        $this->addSql('DROP TABLE work_order_item');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
