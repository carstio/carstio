<?php

namespace App\Controller;

use App\Repository\Car\CarRepository;
use App\Repository\Client\ClientRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\WorkOrderCalendarEventBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        WorkOrderRepository $workOrderRepository,
        ClientRepository $clientRepository,
        CarRepository $carRepository,
    ): Response {
        return $this->render('dashboard/index.html.twig', [
            'openWorkOrders' => $workOrderRepository->countOpen(),
            'completedToday' => $workOrderRepository->countCompletedToday(),
            'totalClients' => $clientRepository->count(),
            'totalCars' => $carRepository->count(),
            'scheduledWorkOrders' => $workOrderRepository->countScheduled(),
        ]);
    }

    #[Route('/dashboard/calendar/events', name: 'app_dashboard_calendar_events', methods: ['GET'])]
    public function calendarEvents(
        Request $request,
        WorkOrderRepository $workOrderRepository,
        WorkOrderCalendarEventBuilder $eventBuilder,
    ): JsonResponse {
        $start = $this->parseRange($request->query->get('start'), '-1 year');
        $end = $this->parseRange($request->query->get('end'), '+1 year');

        $workOrders = $workOrderRepository->findScheduledBetween($start, $end);

        return new JsonResponse($eventBuilder->build($workOrders));
    }

    private function parseRange(?string $value, string $fallback): \DateTimeImmutable
    {
        if ($value !== null && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                // fall through to the default range
            }
        }

        return new \DateTimeImmutable($fallback);
    }
}
