<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the indexes used by the dashboard, statistics and listing filters.
 *
 * CREATE INDEX is identical on both supported platforms; only the DROP
 * syntax differs (MariaDB needs the table name).
 */
final class Version20260929082609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status/date indexes on offer, payment and work_order.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_offer_status ON offer (status)');
        $this->addSql('CREATE INDEX idx_offer_created_at ON offer (created_at)');
        $this->addSql('CREATE INDEX idx_payment_paid_at ON payment (paid_at)');
        $this->addSql('CREATE INDEX idx_work_order_status ON work_order (status)');
        $this->addSql('CREATE INDEX idx_work_order_opened_at ON work_order (opened_at)');
        $this->addSql('CREATE INDEX idx_work_order_scheduled_at ON work_order (scheduled_at)');
        $this->addSql('CREATE INDEX idx_work_order_completed_at ON work_order (completed_at)');
    }

    public function down(Schema $schema): void
    {
        if ($this->platform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP INDEX idx_offer_status');
            $this->addSql('DROP INDEX idx_offer_created_at');
            $this->addSql('DROP INDEX idx_payment_paid_at');
            $this->addSql('DROP INDEX idx_work_order_status');
            $this->addSql('DROP INDEX idx_work_order_opened_at');
            $this->addSql('DROP INDEX idx_work_order_scheduled_at');
            $this->addSql('DROP INDEX idx_work_order_completed_at');

            return;
        }

        $this->addSql('DROP INDEX idx_offer_status ON offer');
        $this->addSql('DROP INDEX idx_offer_created_at ON offer');
        $this->addSql('DROP INDEX idx_payment_paid_at ON payment');
        $this->addSql('DROP INDEX idx_work_order_status ON work_order');
        $this->addSql('DROP INDEX idx_work_order_opened_at ON work_order');
        $this->addSql('DROP INDEX idx_work_order_scheduled_at ON work_order');
        $this->addSql('DROP INDEX idx_work_order_completed_at ON work_order');
    }
}
