<?php

namespace App\Controller;

use App\Entity\Client\Client;
use App\Form\ClientType;
use App\Repository\Client\ClientRepository;
use App\Repository\Sales\OfferRepository;
use App\Repository\Workshop\WorkOrderRepository;
use App\Service\EntityDeleter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'app_client_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $repository): Response
    {
        $search = (string) $request->query->get('q', '');

        return $this->render('client/index.html.twig', [
            'clients' => $repository->findForListing($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($client);
            $em->flush();
            $this->addFlash('success', 'flash.client.created');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Client $client,
        WorkOrderRepository $workOrderRepository,
        OfferRepository $offerRepository,
    ): Response {
        return $this->render('client/show.html.twig', [
            'client' => $client,
            'recent_work_orders' => $workOrderRepository->findRecentForClient($client, 10),
            'recent_offers' => $offerRepository->findRecentForClient($client, 10),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'flash.client.updated');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_client_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityDeleter $deleter): Response
    {
        if ($this->isCsrfTokenValid('delete-client-' . $client->getId(), $request->request->get('_token'))) {
            if ($deleter->delete($client)) {
                $this->addFlash('success', 'flash.client.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
            }
        }

        return $this->redirectToRoute('app_client_index');
    }
}
