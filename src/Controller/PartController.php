<?php

namespace App\Controller;

use App\Entity\Part\Part;
use App\Form\PartType;
use App\Repository\Part\PartRepository;
use App\Service\EntityDeleter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parts')]
final class PartController extends AbstractController
{
    #[Route('', name: 'app_part_index', methods: ['GET'])]
    public function index(Request $request, PartRepository $repository): Response
    {
        $search = (string) $request->query->get('q', '');

        return $this->render('part/index.html.twig', [
            'parts' => $repository->findForListing($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_part_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $part = new Part();
        $form = $this->createForm(PartType::class, $part);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($part);
            $em->flush();
            $this->addFlash('success', 'flash.part.created');

            return $this->redirectToRoute('app_part_show', ['id' => $part->getId()]);
        }

        return $this->render('part/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_part_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Part $part): Response
    {
        return $this->render('part/show.html.twig', [
            'part' => $part,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_part_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Part $part, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PartType::class, $part);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $part->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'flash.part.updated');

            return $this->redirectToRoute('app_part_show', ['id' => $part->getId()]);
        }

        return $this->render('part/edit.html.twig', [
            'part' => $part,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_part_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Part $part, EntityDeleter $deleter): Response
    {
        if ($this->isCsrfTokenValid('delete-part-' . $part->getId(), $request->request->get('_token'))) {
            if ($deleter->delete($part)) {
                $this->addFlash('success', 'flash.part.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_part_show', ['id' => $part->getId()]);
            }
        }

        return $this->redirectToRoute('app_part_index');
    }
}
