<?php

namespace App\Tests\Unit\Service;

use App\Entity\Workshop\Payment;
use App\Entity\Workshop\WorkOrder;
use App\Entity\Workshop\WorkOrderItem;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\WorkOrderItemType;
use App\Service\WorkOrderCalculator;
use PHPUnit\Framework\TestCase;

class WorkOrderCalculatorTest extends TestCase
{
    private WorkOrderCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new WorkOrderCalculator();
    }

    public function testEmptyWorkOrder(): void
    {
        $wo = new WorkOrder();
        $this->calculator->recalculate($wo);

        $this->assertSame('0.00', $wo->getSubtotalLabor());
        $this->assertSame('0.00', $wo->getSubtotalParts());
        $this->assertSame('0.00', $wo->getDiscountAmount());
        $this->assertSame('0.00', $wo->getTaxAmount());
        $this->assertSame('0.00', $wo->getTotalAmount());
        $this->assertSame('0.00', $wo->getPaidAmount());
        $this->assertSame(PaymentStatus::Pending, $wo->getPaymentStatus());
    }

    public function testLaborOnlyCalculation(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Oil change');
        $item->setQuantity('2.00');
        $item->setUnitPrice('30.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('60.00', $wo->getSubtotalLabor());
        $this->assertSame('0.00', $wo->getSubtotalParts());
        $this->assertSame('60.00', $wo->getTotalAmount());
        $this->assertSame('60.00', $item->getLineTotal());
    }

    public function testPartsOnlyCalculation(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Part);
        $item->setName('Oil Filter');
        $item->setQuantity('1.00');
        $item->setUnitPrice('8.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('0.00', $wo->getSubtotalLabor());
        $this->assertSame('8.00', $wo->getSubtotalParts());
        $this->assertSame('8.00', $wo->getTotalAmount());
    }

    public function testMixedItemsCalculation(): void
    {
        $wo = new WorkOrder();

        $labor = new WorkOrderItem();
        $labor->setType(WorkOrderItemType::Labor);
        $labor->setName('Brake service');
        $labor->setQuantity('1.00');
        $labor->setUnitPrice('60.00');
        $wo->addItem($labor);

        $part = new WorkOrderItem();
        $part->setType(WorkOrderItemType::Part);
        $part->setName('Brake pads');
        $part->setQuantity('1.00');
        $part->setUnitPrice('55.00');
        $wo->addItem($part);

        $misc = new WorkOrderItem();
        $misc->setType(WorkOrderItemType::Misc);
        $misc->setName('Disposal fee');
        $misc->setQuantity('1.00');
        $misc->setUnitPrice('5.00');
        $wo->addItem($misc);

        $this->calculator->recalculate($wo);

        $this->assertSame('60.00', $wo->getSubtotalLabor());
        $this->assertSame('60.00', $wo->getSubtotalParts()); // Part + Misc
        $this->assertSame('120.00', $wo->getTotalAmount());
    }

    public function testDiscountCalculation(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setDiscountAmount('10.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('100.00', $wo->getSubtotalLabor());
        $this->assertSame('10.00', $wo->getDiscountAmount());
        $this->assertSame('90.00', $wo->getTotalAmount());
        $this->assertSame('90.00', $item->getLineTotal());
    }

    public function testTaxCalculation(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setTaxRate('20.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('20.00', $wo->getTaxAmount());
        $this->assertSame('120.00', $wo->getTotalAmount());
        $this->assertSame('120.00', $item->getLineTotal());
    }

    public function testDiscountAndTaxCombined(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Part);
        $item->setName('Part');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setDiscountAmount('10.00');
        $item->setTaxRate('20.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        // 100 - 10 = 90, tax on 90 = 18, total = 108
        $this->assertSame('10.00', $wo->getDiscountAmount());
        $this->assertSame('18.00', $wo->getTaxAmount());
        $this->assertSame('108.00', $wo->getTotalAmount());
        $this->assertSame('108.00', $item->getLineTotal());
    }

    public function testPaymentStatusPending(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame(PaymentStatus::Pending, $wo->getPaymentStatus());
    }

    public function testPaymentStatusPaid(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $wo->addItem($item);

        $payment = new Payment();
        $payment->setAmount('100.00');
        $payment->setMethod(PaymentMethod::Cash);
        $payment->setStatus(PaymentStatus::Paid);
        $wo->addPayment($payment);

        $this->calculator->recalculate($wo);

        $this->assertSame('100.00', $wo->getPaidAmount());
        $this->assertSame(PaymentStatus::Paid, $wo->getPaymentStatus());
    }

    public function testPaymentStatusPartiallyPaid(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $wo->addItem($item);

        $payment = new Payment();
        $payment->setAmount('50.00');
        $payment->setMethod(PaymentMethod::Cash);
        $payment->setStatus(PaymentStatus::Paid);
        $wo->addPayment($payment);

        $this->calculator->recalculate($wo);

        $this->assertSame('50.00', $wo->getPaidAmount());
        $this->assertSame(PaymentStatus::PartiallyPaid, $wo->getPaymentStatus());
    }

    public function testPendingPaymentsNotCounted(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $wo->addItem($item);

        $payment = new Payment();
        $payment->setAmount('100.00');
        $payment->setMethod(PaymentMethod::Cash);
        $payment->setStatus(PaymentStatus::Pending);
        $wo->addPayment($payment);

        $this->calculator->recalculate($wo);

        $this->assertSame('0.00', $wo->getPaidAmount());
        $this->assertSame(PaymentStatus::Pending, $wo->getPaymentStatus());
    }

    public function testMultipleQuantity(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Part);
        $item->setName('Spark Plug');
        $item->setQuantity('4.00');
        $item->setUnitPrice('9.50');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('38.00', $item->getLineTotal());
        $this->assertSame('38.00', $wo->getTotalAmount());
    }

    public function testZeroDiscountAndTaxBehaveLikeOmitted(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        $item->setDiscountAmount('0.00');
        $item->setTaxRate('0.00');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('0.00', $wo->getDiscountAmount());
        $this->assertSame('0.00', $wo->getTaxAmount());
        $this->assertSame('100.00', $wo->getTotalAmount());
        $this->assertSame('100.00', $item->getLineTotal());
    }

    public function testCalculatorToleratesEmptyDiscountAndTaxRateStrings(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Labor);
        $item->setName('Service');
        $item->setQuantity('1.00');
        $item->setUnitPrice('100.00');
        // Simulate edge case where the fields were somehow persisted as
        // empty strings (e.g. data migration) - calculator must not blow up.
        $item->setDiscountAmount('');
        $item->setTaxRate('');
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('0.00', $wo->getDiscountAmount());
        $this->assertSame('0.00', $wo->getTaxAmount());
        $this->assertSame('100.00', $wo->getTotalAmount());
        $this->assertSame('100.00', $item->getLineTotal());
    }

    public function testOnlyDiscountWithoutTaxCalculatesCorrectly(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Part);
        $item->setName('Filter');
        $item->setQuantity('1.00');
        $item->setUnitPrice('50.00');
        $item->setDiscountAmount('5.00');
        // taxRate intentionally untouched => default '0.00'
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('5.00', $wo->getDiscountAmount());
        $this->assertSame('0.00', $wo->getTaxAmount());
        $this->assertSame('45.00', $wo->getTotalAmount());
        $this->assertSame('45.00', $item->getLineTotal());
    }

    public function testOnlyTaxWithoutDiscountCalculatesCorrectly(): void
    {
        $wo = new WorkOrder();

        $item = new WorkOrderItem();
        $item->setType(WorkOrderItemType::Part);
        $item->setName('Filter');
        $item->setQuantity('1.00');
        $item->setUnitPrice('50.00');
        $item->setTaxRate('10.00');
        // discountAmount intentionally untouched => default '0.00'
        $wo->addItem($item);

        $this->calculator->recalculate($wo);

        $this->assertSame('0.00', $wo->getDiscountAmount());
        $this->assertSame('5.00', $wo->getTaxAmount());
        $this->assertSame('55.00', $wo->getTotalAmount());
        $this->assertSame('55.00', $item->getLineTotal());
    }
}
