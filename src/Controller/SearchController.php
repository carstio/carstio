<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Search\GlobalSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/search')]
final class SearchController extends AbstractController
{
    #[Route('/autocomplete', name: 'app_search_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, GlobalSearchService $search): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < GlobalSearchService::MIN_QUERY_LENGTH) {
            return $this->json([
                'query' => $query,
                'groups' => [],
            ]);
        }

        return $this->json($search->search($query));
    }
}
