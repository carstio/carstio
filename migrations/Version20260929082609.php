<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260929082609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
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
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_offer_status ON offer');
        $this->addSql('DROP INDEX idx_offer_created_at ON offer');
        $this->addSql('DROP INDEX idx_payment_paid_at ON payment');
        $this->addSql('DROP INDEX idx_work_order_status ON work_order');
        $this->addSql('DROP INDEX idx_work_order_opened_at ON work_order');
        $this->addSql('DROP INDEX idx_work_order_scheduled_at ON work_order');
        $this->addSql('DROP INDEX idx_work_order_completed_at ON work_order');
    }
}
