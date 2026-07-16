<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Car\Car;
use App\Repository\Car\CarRepository;
use App\Repository\Client\ClientRepository;
use App\Repository\Client\CompanyRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Global header search across Clients, Cars, and Companies.
 *
 * Returns a stable view-ready array shape so the autocomplete endpoint can
 * encode it directly to JSON. The shape is:
 *   [
 *     'query'  => '...',
 *     'groups' => [
 *       ['type' => 'client',  'label' => 'Clients',   'items' => [...]],
 *       ['type' => 'car',     'label' => 'Cars',      'items' => [...]],
 *       ['type' => 'company', 'label' => 'Companies', 'items' => [...]],
 *     ],
 *   ]
 *
 * Each item is `{label, description, url}` - URLs are generated server-side
 * to keep route names out of the JavaScript controller.
 */
final class GlobalSearchService
{
    public const MIN_QUERY_LENGTH = 2;
    public const DEFAULT_LIMIT_PER_GROUP = 5;

    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly CarRepository $carRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{query: string, groups: list<array{type: string, label: string, items: list<array{label: string, description: string, url: string}>}>}
     */
    public function search(string $query, int $perGroup = self::DEFAULT_LIMIT_PER_GROUP): array
    {
        $trimmed = trim($query);

        $groups = [
            $this->buildGroup('client', 'search.group.clients', $this->clientItems($trimmed, $perGroup)),
            $this->buildGroup('car', 'search.group.cars', $this->carItems($trimmed, $perGroup)),
            $this->buildGroup('company', 'search.group.companies', $this->companyItems($trimmed, $perGroup)),
        ];

        return [
            'query' => $trimmed,
            'groups' => $groups,
        ];
    }

    /**
     * @param list<array{label: string, description: string, url: string}> $items
     *
     * @return array{type: string, label: string, items: list<array{label: string, description: string, url: string}>}
     */
    private function buildGroup(string $type, string $labelKey, array $items): array
    {
        return [
            'type' => $type,
            'label' => $this->translator->trans($labelKey),
            'items' => $items,
        ];
    }

    /**
     * @return list<array{label: string, description: string, url: string}>
     */
    private function clientItems(string $query, int $limit): array
    {
        if ($query === '' || $limit < 1) {
            return [];
        }

        $items = [];
        foreach ($this->clientRepository->searchByQuery($query, $limit) as $client) {
            $items[] = [
                'label' => (string) $client->getName(),
                'description' => $this->joinDescription([$client->getEmail(), $client->getPhone()]),
                'url' => $this->urlGenerator->generate('app_client_show', ['id' => $client->getId()]),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{label: string, description: string, url: string}>
     */
    private function carItems(string $query, int $limit): array
    {
        if ($query === '' || $limit < 1) {
            return [];
        }

        $items = [];
        foreach ($this->carRepository->searchByQuery($query, $limit) as $car) {
            $items[] = [
                'label' => $this->carLabel($car),
                'description' => $this->joinDescription([
                    $car->getLicensePlate(),
                    $car->getClient()?->getName(),
                ]),
                'url' => $this->urlGenerator->generate('app_car_show', ['id' => $car->getId()]),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{label: string, description: string, url: string}>
     */
    private function companyItems(string $query, int $limit): array
    {
        if ($query === '' || $limit < 1) {
            return [];
        }

        $items = [];
        foreach ($this->companyRepository->searchByQuery($query, $limit) as $company) {
            $items[] = [
                'label' => (string) $company->getName(),
                'description' => $this->joinDescription([$company->getIdentifier(), $company->getVatIdentifier()]),
                'url' => $this->urlGenerator->generate('app_company_show', ['id' => $company->getId()]),
            ];
        }

        return $items;
    }

    private function carLabel(Car $car): string
    {
        $display = trim($car->getDisplayName());

        return $display !== '' ? $display : ('#' . (int) $car->getId());
    }

    /**
     * @param list<?string> $parts
     */
    private function joinDescription(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            if ($part === null) {
                continue;
            }
            $value = trim($part);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return implode(' · ', $clean);
    }
}
