<?php

namespace App\Controller;

use App\Entity\Car\Car;
use App\Entity\Client\Client;
use App\Form\CarType;
use App\Repository\Car\CarRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\EntityDeleter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cars')]
final class CarController extends AbstractController
{
    #[Route('', name: 'app_car_index', methods: ['GET'])]
    public function index(Request $request, CarRepository $repository): Response
    {
        $search = (string) $request->query->get('q', '');

        return $this->render('car/index.html.twig', [
            'cars' => $repository->findForListing($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_car_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $car = new Car();

        if ($clientId = $request->query->get('client')) {
            $client = $em->getRepository(Client::class)->find($clientId);
            if ($client) {
                $car->setClient($client);
            }
        }

        $form = $this->createForm(CarType::class, $car);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($car);
            $em->flush();
            $this->addFlash('success', 'flash.car.created');

            return $this->redirectToRoute('app_car_show', ['id' => $car->getId()]);
        }

        return $this->render('car/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_car_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Car $car, WorkOrderRepository $workOrders): Response
    {
        return $this->render('car/show.html.twig', [
            'car' => $car,
            'work_order_history' => $workOrders->findHistoryForCar($car),
            'latest_mileage_work_order' => $workOrders->findLatestWithMileageForCar($car),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_car_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CarType::class, $car);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'flash.car.updated');

            return $this->redirectToRoute('app_car_show', ['id' => $car->getId()]);
        }

        return $this->render('car/edit.html.twig', [
            'car' => $car,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_car_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Car $car, EntityDeleter $deleter): Response
    {
        if ($this->isCsrfTokenValid('delete-car-' . $car->getId(), $request->request->get('_token'))) {
            if ($deleter->delete($car)) {
                $this->addFlash('success', 'flash.car.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_car_show', ['id' => $car->getId()]);
            }
        }

        return $this->redirectToRoute('app_car_index');
    }
}
