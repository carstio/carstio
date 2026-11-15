<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What the workshop pays for parts, and what it earns on them, is the
 * owner's business: administrators see and edit it, mechanics neither see
 * it nor can change it - and their edits must not wipe it either.
 */
final class CostVisibilityTest extends WebTestCase
{
    private const ADMIN = 'admin@carstio.local';
    private const MECHANIC = 'mechanic@carstio.local';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testThePartsListShowsCostAndProfitToAdministratorsOnly(): void
    {
        $this->logInAs(self::ADMIN);
        $crawler = $this->client->request('GET', '/parts');
        self::assertResponseIsSuccessful();
        self::assertCount(8, $crawler->filter('table.data-table thead th'));
        self::assertStringContainsString('25.00', $crawler->filter('table.data-table tbody')->text());

        $this->logInAs(self::MECHANIC);
        $crawler = $this->client->request('GET', '/parts');
        self::assertResponseIsSuccessful();
        self::assertCount(6, $crawler->filter('table.data-table thead th'));
        $table = $crawler->filter('table.data-table tbody')->text();
        self::assertStringContainsString('55.00', $table, 'The selling price stays visible.');
        self::assertStringNotContainsString('25.00', $table, 'Cost of the brake pads.');
        self::assertStringNotContainsString('30.00', $table, 'Profit on the brake pads.');
    }

    public function testThePartPageShowsCostProfitAndMarginToAdministratorsOnly(): void
    {
        $partId = $this->partId('BP-002');

        $this->logInAs(self::ADMIN);
        $text = $this->client->request('GET', '/parts/' . $partId)->filter('.row.g-3')->text();
        self::assertStringContainsString('25.00', $text);
        self::assertStringContainsString('30.00', $text);
        self::assertStringContainsString('54.5%', $text);

        $this->logInAs(self::MECHANIC);
        $text = $this->client->request('GET', '/parts/' . $partId)->filter('.row.g-3')->text();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('55.00', $text, 'The selling price stays visible.');
        self::assertStringNotContainsString('25.00', $text);
        self::assertStringNotContainsString('30.00', $text);
        self::assertStringNotContainsString('54.5', $text);
    }

    public function testAMechanicEditsAPartWithoutSeeingOrLosingItsCost(): void
    {
        $partId = $this->partId('BP-002');

        $this->logInAs(self::ADMIN);
        self::assertCount(1, $this->client->request('GET', '/parts/' . $partId . '/edit')->filter('#part_costPrice'));

        $this->logInAs(self::MECHANIC);
        $crawler = $this->client->request('GET', '/parts/' . $partId . '/edit');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#part_costPrice'));
        self::assertStringNotContainsString('25.00', (string) $this->client->getResponse()->getContent());

        $form = $crawler->filter('form[name="part"]')->form();
        $form['part[name]'] = 'Front Brake Pads (renamed)';
        $this->client->submit($form);
        self::assertResponseRedirects('/parts/' . $partId);

        self::assertSame(
            ['name' => 'Front Brake Pads (renamed)', 'cost_price' => '25.00'],
            $this->row('SELECT name, cost_price FROM part WHERE id = ?', $partId),
        );
    }

    public function testAMechanicCannotSetAPartsCostWithAHandMadeRequest(): void
    {
        $partId = $this->partId('BP-002');

        $this->logInAs(self::MECHANIC);
        $crawler = $this->client->request('GET', '/parts/' . $partId . '/edit');
        $values = $crawler->filter('form[name="part"]')->form()->getPhpValues();
        $values['part']['costPrice'] = '0.01';
        $this->client->request('POST', '/parts/' . $partId . '/edit', $values);

        self::assertSame(['cost_price' => '25.00'], $this->row('SELECT cost_price FROM part WHERE id = ?', $partId));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function documents(): iterable
    {
        yield 'work order' => ['/work-orders/', 'SELECT work_order_id AS id, cost_price FROM work_order_item WHERE cost_price IS NOT NULL ORDER BY id'];
        yield 'offer' => ['/offers/', 'SELECT i.offer_id AS id, i.cost_price FROM offer_item i JOIN offer o ON o.id = i.offer_id WHERE i.cost_price IS NOT NULL AND o.status = \'draft\' ORDER BY i.id'];
    }

    #[DataProvider('documents')]
    public function testThePartsMarginIsShownToAdministratorsOnly(string $path, string $sql): void
    {
        $id = (int) $this->row($sql)['id'];

        $this->logInAs(self::ADMIN);
        self::assertCount(1, $this->client->request('GET', $path . $id)->filter('[data-parts-margin]'));

        $this->logInAs(self::MECHANIC);
        $crawler = $this->client->request('GET', $path . $id);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-parts-margin]'));
    }

    #[DataProvider('documents')]
    public function testAMechanicEditsADocumentWithoutSeeingOrLosingItemCosts(string $path, string $sql): void
    {
        $before = $this->row($sql);
        $id = (int) $before['id'];
        $table = str_contains($path, 'offers') ? 'offer_item' : 'work_order_item';
        $owner = str_contains($path, 'offers') ? 'offer_id' : 'work_order_id';
        $costs = fn (): array => $this->connection()->fetchFirstColumn(
            sprintf('SELECT cost_price FROM %s WHERE %s = ? ORDER BY id', $table, $owner),
            [$id],
        );
        $costsBefore = $costs();
        self::assertContains($before['cost_price'], $costsBefore);

        $this->logInAs(self::ADMIN);
        $crawler = $this->client->request('GET', $path . $id . '/edit');
        self::assertGreaterThan(0, $crawler->filter('input[name$="[costPrice]"]')->count());

        $this->logInAs(self::MECHANIC);
        $crawler = $this->client->request('GET', $path . $id . '/edit');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('input[name$="[costPrice]"]'));
        self::assertStringNotContainsString('costPrice', (string) $this->client->getResponse()->getContent(), 'Not in the template for new rows either.');

        // Saved as it is: every item keeps the cost an administrator gave it.
        $this->client->submit($crawler->filter('form')->reduce(
            static fn ($node) => str_ends_with((string) $node->attr('action'), '/edit') || $node->attr('action') === null || $node->attr('action') === '',
        )->first()->form());
        self::assertResponseRedirects($path . $id);
        self::assertSame($costsBefore, $costs());
    }

    private function logInAs(string $email): void
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        $this->client->loginUser($user);
    }

    private function partId(string $sku): int
    {
        return (int) $this->row('SELECT id FROM part WHERE sku = ?', $sku)['id'];
    }

    /**
     * @return array<string, string>
     */
    private function row(string $sql, int|string ...$parameters): array
    {
        $row = $this->connection()->fetchAssociative($sql, $parameters);
        self::assertIsArray($row, $sql);

        return array_map(strval(...), $row);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
