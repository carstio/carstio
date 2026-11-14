<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Client\Client;
use App\Entity\Client\Company;
use App\Entity\Part\Part;
use App\Repository\Client\ClientRepository;
use App\Repository\Client\CompanyRepository;
use App\Repository\Part\PartRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Searching must ignore case for every alphabet, on every database engine.
 *
 * MariaDB's default collation hides the difference; PostgreSQL compares
 * exactly, so the search term has to be lower-cased with a function that
 * knows more than ASCII before it meets LOWER(column).
 */
final class NonAsciiSearchTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    #[DataProvider('names')]
    public function testClientsAreFoundWhateverCaseTheSearchIsTypedIn(string $name, string $term): void
    {
        $this->entityManager->persist((new Client())->setName($name)->setPhone('+359 88 000 0000'));
        $this->entityManager->flush();
        $clients = static::getContainer()->get(ClientRepository::class);

        self::assertContains($name, array_map(static fn (Client $client): ?string => $client->getName(), $clients->findForListing($term)));
        self::assertContains($name, array_map(static fn (Client $client): ?string => $client->getName(), $clients->searchByQuery($term, 10)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'Cyrillic, as written' => ['Иван Петров', 'Иван'];
        yield 'Cyrillic, upper case' => ['Иван Петров', 'ИВАН ПЕТ'];
        yield 'Cyrillic, lower case' => ['Иван Петров', 'петров'];
        yield 'Latin with diacritics' => ['Ömer Çelik', 'ÖMER'];
        yield 'German sharp s neighbours' => ['Jürgen Müller', 'MÜLLER'];
        yield 'plain ASCII still works' => ['John Smith', 'SMITH'];
    }

    public function testPartsAndCompaniesAreSearchedTheSameWay(): void
    {
        $part = (new Part())->setName('Маслен филтър')->setSku('MF-' . bin2hex(random_bytes(3)));
        $owner = (new Client())->setName('Собственик')->setPhone('+359 88 000 0001');
        $company = (new Company())->setClient($owner)->setName('Авто Сервиз ЕООД')->setCity('София')->setAddress('ул. Витоша 1')->setIdentifier('205123456');
        $this->entityManager->persist($owner);
        $this->entityManager->persist($part);
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $parts = static::getContainer()->get(PartRepository::class)->findForListing('МАСЛЕН');
        self::assertContains('Маслен филтър', array_map(static fn (Part $found): ?string => $found->getName(), $parts));

        $companies = static::getContainer()->get(CompanyRepository::class)->searchByQuery('СЕРВИЗ', 10);
        self::assertContains('Авто Сервиз ЕООД', array_map(static fn (Company $found): ?string => $found->getName(), $companies));
    }
}
