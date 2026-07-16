<?php

namespace App\Controller;

use App\Entity\Workshop\Payment;
use App\Entity\Workshop\WorkOrder;
use App\Form\PaymentType;
use App\Repository\Workshop\PaymentRepository;
use App\Service\EntityDeleter;
use App\Service\WorkOrderCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{
    #[Route('/payments', name: 'app_payment_index', methods: ['GET'])]
    public function index(PaymentRepository $repository): Response
    {
        return $this->render('payment/index.html.twig', [
            'payments' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/payments/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, WorkOrderCalculator $calculator): Response
    {
        return $this->handleNew($request, null, $em, $calculator);
    }

    #[Route(
        '/work-orders/{workOrder}/payments/new',
        name: 'app_work_order_payment_new',
        requirements: ['workOrder' => '\d+'],
        methods: ['GET', 'POST'],
    )]
    public function newForWorkOrder(
        Request $request,
        #[MapEntity(id: 'workOrder')] WorkOrder $workOrder,
        EntityManagerInterface $em,
        WorkOrderCalculator $calculator,
    ): Response {
        return $this->handleNew($request, $workOrder, $em, $calculator);
    }

    #[Route('/payments/{id}', name: 'app_payment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/payments/{id}/delete', name: 'app_payment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $em, EntityDeleter $deleter, WorkOrderCalculator $calculator): Response
    {
        if ($this->isCsrfTokenValid('delete-payment-' . $payment->getId(), $request->request->get('_token'))) {
            $workOrder = $payment->getWorkOrder();

            if ($deleter->delete($payment)) {
                $calculator->recalculate($workOrder);
                $em->flush();

                $this->addFlash('success', 'flash.payment.deleted');
            } else {
                $this->addFlash('error', 'flash.delete.restricted');

                return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
            }
        }

        return $this->redirectToRoute('app_payment_index');
    }

    private function handleNew(
        Request $request,
        ?WorkOrder $workOrder,
        EntityManagerInterface $em,
        WorkOrderCalculator $calculator,
    ): Response {
        $payment = new Payment();
        if ($workOrder !== null) {
            $payment->setWorkOrder($workOrder);
        }

        $form = $this->createForm(PaymentType::class, $payment, [
            'work_order_locked' => $workOrder !== null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($payment);
            $calculator->recalculate($payment->getWorkOrder());
            $em->flush();

            $this->addFlash('success', 'flash.payment.created');

            return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
        }

        return $this->render('payment/new.html.twig', [
            'form' => $form,
        ]);
    }
}
