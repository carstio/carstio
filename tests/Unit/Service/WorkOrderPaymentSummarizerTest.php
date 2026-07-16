<?php

namespace App\Tests\Unit\Service;

use App\Entity\Workshop\WorkOrder;
use App\Enum\PaymentStatus;
use App\Service\Workshop\WorkOrderPaymentSummarizer;
use PHPUnit\Framework\TestCase;

class WorkOrderPaymentSummarizerTest extends TestCase
{
    private WorkOrderPaymentSummarizer $summarizer;

    protected function setUp(): void
    {
        $this->summarizer = new WorkOrderPaymentSummarizer();
    }

    public function testSummarizesUnpaidWorkOrder(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setTotalAmount('150.00');
        $workOrder->setPaidAmount('0.00');
        $workOrder->setPaymentStatus(PaymentStatus::Pending);

        $summary = $this->summarizer->summarize($workOrder);

        $this->assertSame('150.00', $summary->totalAmount);
        $this->assertSame('0.00', $summary->paidAmount);
        $this->assertSame('150.00', $summary->remainingAmount);
        $this->assertSame(PaymentStatus::Pending, $summary->status);
    }

    public function testSummarizesPartiallyPaidWorkOrder(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setTotalAmount('153.00');
        $workOrder->setPaidAmount('50.00');
        $workOrder->setPaymentStatus(PaymentStatus::PartiallyPaid);

        $summary = $this->summarizer->summarize($workOrder);

        $this->assertSame('153.00', $summary->totalAmount);
        $this->assertSame('50.00', $summary->paidAmount);
        $this->assertSame('103.00', $summary->remainingAmount);
        $this->assertSame(PaymentStatus::PartiallyPaid, $summary->status);
    }

    public function testRemainingNeverGoesNegative(): void
    {
        $workOrder = new WorkOrder();
        $workOrder->setTotalAmount('80.00');
        $workOrder->setPaidAmount('100.00');
        $workOrder->setPaymentStatus(PaymentStatus::Paid);

        $summary = $this->summarizer->summarize($workOrder);

        $this->assertSame('0.00', $summary->remainingAmount);
        $this->assertSame(PaymentStatus::Paid, $summary->status);
    }
}
