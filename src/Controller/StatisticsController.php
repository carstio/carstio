<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Statistics\StatisticsPeriodResolver;
use App\Service\Statistics\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Read-only statistics dashboard.
 *
 * Restricted to admins because it surfaces aggregated revenue figures
 * that mechanics do not need for day-to-day operations and that the
 * project considers sensitive company data.
 */
#[Route('/statistics')]
#[IsGranted('ROLE_ADMIN')]
final class StatisticsController extends AbstractController
{
    #[Route('', name: 'app_statistics_index', methods: ['GET'])]
    public function index(
        Request $request,
        StatisticsPeriodResolver $periodResolver,
        StatisticsService $statisticsService,
    ): Response {
        $period = $periodResolver->resolve($request);
        $dashboard = $statisticsService->buildDashboard($period);

        return $this->render('statistics/index.html.twig', [
            'dashboard' => $dashboard,
            'presets' => StatisticsPeriodResolver::presets(),
            'preset_default' => StatisticsPeriodResolver::PRESET_CUSTOM,
            'from' => $request->query->get('from', ''),
            'to' => $request->query->get('to', ''),
        ]);
    }
}
