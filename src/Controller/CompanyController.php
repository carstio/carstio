<?php

namespace App\Controller;

use App\Entity\Client\Client;
use App\Entity\Client\Company;
use App\Form\CompanyType;
use App\Repository\Client\CompanyRepository;
use App\Service\EntityDeleter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/companies')]
final class CompanyController extends AbstractController
{
    #[Route('', name: 'app_company_index', methods: ['GET'])]
    public function index(CompanyRepository $repository): Response
    {
        return $this->render('company/index.html.twig', [
            'companies' => $repository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $company = new Company();

        if ($clientId = $request->query->get('client')) {
            $client = $em->getRepository(Client::class)->find($clientId);
            if ($client) {
                $company->setClient($client);
            }
        }

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($company);
            $em->flush();
            $this->addFlash('success', 'flash.company.created');

            return $this->redirectToRoute('app_company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_company_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Company $company): Response
    {
        return $this->render('company/show.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_company_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Company $company, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'flash.company.updated');

            return $this->redirectToRoute('app_company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/edit.html.twig', [
            'company' => $company,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_company_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Company $company, EntityDeleter $deleter): Response
    {
        if ($this->isCsrfTokenValid('delete-company-' . $company->getId(), $request->request->get('_token'))) {
            if ($deleter->delete($company)) {
                $this->addFlash('success', 'flash.company.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_company_show', ['id' => $company->getId()]);
            }
        }

        return $this->redirectToRoute('app_company_index');
    }
}
