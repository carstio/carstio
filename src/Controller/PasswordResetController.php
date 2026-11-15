<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\NewPasswordType;
use App\Form\PasswordResetRequestType;
use App\Security\PasswordReset\PasswordResetLinks;
use App\Security\PasswordReset\PasswordResetThrottle;
use App\Service\User\PasswordResetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Forgot my password", for people who are not logged in. Public pages:
 * see access_control in config/packages/security.yaml.
 */
final class PasswordResetController extends AbstractController
{
    #[Route('/reset-password', name: 'app_password_reset_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        PasswordResetManager $passwordReset,
        PasswordResetThrottle $throttle,
        TranslatorInterface $translator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (!$passwordReset->isAvailable()) {
            return $this->render('password_reset/unavailable.html.twig');
        }

        $form = $this->createForm(PasswordResetRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();

            if (!$throttle->allows($request->getClientIp(), $email)) {
                $form->addError(new FormError($translator->trans('password_reset.request.too_many')));

                return $this->render('password_reset/request.html.twig', ['form' => $form], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
            }

            $passwordReset->request($email);

            // The same answer whether or not the address has an account.
            return $this->redirectToRoute('app_password_reset_sent');
        }

        return $this->render('password_reset/request.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password/sent', name: 'app_password_reset_sent', methods: ['GET'])]
    public function sent(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('password_reset/sent.html.twig', [
            'minutes' => PasswordResetLinks::LIFETIME_MINUTES,
        ]);
    }

    #[Route(
        '/reset-password/{id}/{expires}/{signature}',
        name: PasswordResetLinks::ROUTE,
        requirements: ['id' => '\d{1,18}', 'expires' => '\d{1,18}', 'signature' => '[A-Za-z0-9_~-]{1,200}'],
        methods: ['GET', 'POST'],
    )]
    public function confirm(
        Request $request,
        int $id,
        int $expires,
        string $signature,
        PasswordResetManager $passwordReset,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = $passwordReset->userForLink($id, $expires, $signature);
        if ($user === null) {
            $this->addFlash('error', 'flash.password_reset.invalid_link');

            return $this->redirectToRoute('app_password_reset_request');
        }

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordReset->setNewPassword($user, (string) $form->get('plainPassword')->getData());
            $this->addFlash('success', 'flash.password_reset.done');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('password_reset/confirm.html.twig', [
            'form' => $form,
            'email' => $user->getEmail(),
        ]);
    }
}
