<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The browser limits what can be typed into a line of a work order or an
 * offer; these tests send the values a browser would not, and expect the
 * server to refuse them and leave the document as it was.
 */
final class ItemLimitsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@carstio.local']);
        self::assertNotNull($user);
        $this->client->loginUser($user);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function refusedLines(): iterable
    {
        foreach (['work order', 'offer'] as $document) {
            yield $document . ': tax rate above 100%' => [$document, ['taxRate' => '500']];
            yield $document . ': tax rate that does not fit the column' => [$document, ['taxRate' => '5000']];
            yield $document . ': negative tax rate' => [$document, ['taxRate' => '-20']];
            yield $document . ': negative discount (a hidden surcharge)' => [$document, ['discountAmount' => '-50']];
            yield $document . ': discount larger than the line' => [$document, ['quantity' => '2', 'unitPrice' => '10', 'discountAmount' => '20.01']];
            yield $document . ': quantity that does not fit the column' => [$document, ['quantity' => '100000000']];
            yield $document . ': price that does not fit the column' => [$document, ['unitPrice' => '100000000']];
            yield $document . ': line worth more than can be stored' => [$document, ['quantity' => '99999', 'unitPrice' => '99999']];
            yield $document . ': negative quantity' => [$document, ['quantity' => '-1']];
            yield $document . ': negative price' => [$document, ['unitPrice' => '-1']];
            yield $document . ': negative cost' => [$document, ['costPrice' => '-1']];
            yield $document . ': cost that does not fit the column' => [$document, ['costPrice' => '100000000']];
        }
    }

    /**
     * @param array<string, string> $line
     */
    #[DataProvider('refusedLines')]
    public function testALineWithImpossibleValuesIsRefused(string $document, array $line): void
    {
        [$path, $id] = $this->document($document);
        $before = $this->stored($document, $id);

        $this->submitFirstLine($path, $id, $line);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->stored($document, $id), 'Nothing was saved.');
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function acceptedLines(): iterable
    {
        foreach (['work order', 'offer'] as $document) {
            yield $document . ': no tax' => [$document, ['quantity' => '2', 'unitPrice' => '10', 'discountAmount' => '0', 'taxRate' => '0'], '20.00'];
            yield $document . ': 100% tax' => [$document, ['quantity' => '2', 'unitPrice' => '10', 'discountAmount' => '0', 'taxRate' => '100'], '40.00'];
            yield $document . ': discount of the whole line (a free item)' => [$document, ['quantity' => '2', 'unitPrice' => '10', 'discountAmount' => '20', 'taxRate' => '20'], '0.00'];
            yield $document . ': ordinary line' => [$document, ['quantity' => '1.5', 'unitPrice' => '40', 'discountAmount' => '5', 'taxRate' => '20'], '66.00'];
            yield $document . ': a very large line' => [$document, ['quantity' => '1', 'unitPrice' => '9999999.99', 'discountAmount' => '0', 'taxRate' => '0'], '9999999.99'];
        }
    }

    /**
     * @param array<string, string> $line
     */
    #[DataProvider('acceptedLines')]
    public function testAnOrdinaryLineIsStillAccepted(string $document, array $line, string $expectedLineTotal): void
    {
        [$path, $id] = $this->document($document);

        $itemId = $this->submitFirstLine($path, $id, $line);

        self::assertResponseRedirects($path . $id);
        $table = $document === 'offer' ? 'offer_item' : 'work_order_item';
        self::assertSame(
            $expectedLineTotal,
            (string) $this->connection()->fetchOne(sprintf('SELECT line_total FROM %s WHERE id = ?', $table), [$itemId]),
        );
    }

    public function testTheErrorIsShownNextToTheField(): void
    {
        [$path, $id] = $this->document('work order');

        $this->submitFirstLine($path, $id, ['taxRate' => '500']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
        self::assertSelectorExists('input.is-invalid[name$="[taxRate]"]');
    }

    public function testTheDiscountErrorSaysWhatIsWrong(): void
    {
        [$path, $id] = $this->document('offer');

        $this->submitFirstLine($path, $id, ['quantity' => '1', 'unitPrice' => '10', 'discountAmount' => '15']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('input.is-invalid[name$="[discountAmount]"]');
        self::assertSelectorTextContains('.invalid-feedback', 'The discount cannot be more than the line amount');
    }

    /**
     * Every figure of the line is within its own limit, yet with tax the
     * document would be worth twice what the total column can hold.
     */
    #[DataProvider('bothDocuments')]
    public function testADocumentThatAddsUpToTooMuchIsRefusedWithAVisibleError(string $document): void
    {
        [$path, $id] = $this->document($document);
        $before = $this->stored($document, $id);

        $this->submitFirstLine($path, $id, ['quantity' => '1', 'unitPrice' => '99999999.99', 'discountAmount' => '0', 'taxRate' => '100']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-danger', 'The total of this document is too large to be saved.');
        self::assertSame($before, $this->stored($document, $id), 'Nothing was saved.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bothDocuments(): iterable
    {
        yield 'work order' => ['work order'];
        yield 'offer' => ['offer'];
    }

    public function testAPartPriceThatCannotBeStoredIsRefused(): void
    {
        $partId = (int) $this->connection()->fetchOne("SELECT id FROM part WHERE sku = 'BP-002'");

        foreach (['sellPrice' => '100000000', 'costPrice' => '100000000'] as $field => $value) {
            $crawler = $this->client->request('GET', '/parts/' . $partId . '/edit');
            $form = $crawler->filter('form[name="part"]')->form();
            $form['part[' . $field . ']'] = $value;
            $this->client->submit($form);

            self::assertResponseStatusCodeSame(422, $field);
            self::assertSelectorExists('#part_' . $field . '.is-invalid');
        }

        self::assertSame(
            ['cost_price' => '25.00', 'sell_price' => '55.00'],
            $this->connection()->fetchAssociative('SELECT cost_price, sell_price FROM part WHERE id = ?', [$partId]),
        );
    }

    public function testAPaymentAmountThatCannotBeStoredIsRefused(): void
    {
        [, $workOrderId] = $this->document('work order');
        $count = fn (): int => (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM payment WHERE work_order_id = ?', [$workOrderId]);
        $before = $count();

        $crawler = $this->client->request('GET', '/work-orders/' . $workOrderId . '/payments/new');
        $form = $crawler->filter('form[name="payment"]')->form();
        $form['payment[amount]'] = '100000000';
        $form['payment[method]'] = 'cash';
        $form['payment[status]'] = 'paid';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#payment_amount.is-invalid');
        self::assertSame($before, $count());
    }

    /**
     * Sends the edit form as the page rendered it, with the first line
     * changed, the way a hand-made request would.
     *
     * @param array<string, string> $line
     *
     * @return int the id of the line that was changed
     */
    private function submitFirstLine(string $path, int $id, array $line): int
    {
        $crawler = $this->client->request('GET', $path . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->reduce(
            static fn ($node) => $node->filter('input[name$="[taxRate]"]')->count() > 0,
        )->first()->form();
        $values = $form->getPhpValues();
        $root = array_key_first($values);
        $key = array_key_first($values[$root]['items']);
        $values[$root]['items'][$key] = array_merge($values[$root]['items'][$key], $line);

        $this->client->request('POST', $path . $id . '/edit', $values);

        $table = $path === '/offers/' ? 'offer_item' : 'work_order_item';
        $owner = $path === '/offers/' ? 'offer_id' : 'work_order_id';

        return (int) $this->connection()->fetchOne(
            sprintf('SELECT id FROM %s WHERE %s = ? ORDER BY sort_order, id', $table, $owner),
            [$id],
        );
    }

    /**
     * A document that can be edited and has a part line (so the cost field
     * is there too).
     *
     * @return array{string, int}
     */
    private function document(string $document): array
    {
        $id = $document === 'offer'
            ? $this->connection()->fetchOne("SELECT o.id FROM offer o JOIN offer_item i ON i.offer_id = o.id WHERE o.status = 'draft' ORDER BY o.id")
            : $this->connection()->fetchOne('SELECT work_order_id FROM work_order_item WHERE cost_price IS NOT NULL ORDER BY id');
        self::assertNotFalse($id);

        return [$document === 'offer' ? '/offers/' : '/work-orders/', (int) $id];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stored(string $document, int $id): array
    {
        [$table, $items, $owner] = $document === 'offer'
            ? ['offer', 'offer_item', 'offer_id']
            : ['work_order', 'work_order_item', 'work_order_id'];

        return [
            ...$this->connection()->fetchAllAssociative(sprintf('SELECT total_amount, tax_amount, discount_amount FROM %s WHERE id = ?', $table), [$id]),
            ...$this->connection()->fetchAllAssociative(
                sprintf('SELECT id, quantity, unit_price, discount_amount, tax_rate, cost_price, line_total FROM %s WHERE %s = ? ORDER BY id', $items, $owner),
                [$id],
            ),
        ];
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
