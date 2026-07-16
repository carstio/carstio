<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\User\LastAdminException;
use App\Service\User\UserAccountManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User Management - admins only. Mechanic accounts cannot reach this
 * controller because of the class-level IsGranted attribute.
 *
 * Self-service guards (don't deactivate yourself, don't demote the last
 * admin) live here in the controller because they map naturally to flash
 * messages on a redirect; the deeper rule "no zero-admin state" is owned
 * by {@see UserAccountManager}.
 */
#[Route('/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAllOrderedByName(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserAccountManager $manager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('role')->getData();
            $plainPassword = (string) $form->get('plainPassword')->getData();

            $manager->create($user, $plainPassword, $role);
            $this->addFlash('success', 'flash.user.created');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        UserAccountManager $manager,
        #[\Symfony\Component\Security\Http\Attribute\CurrentUser] UserInterface $currentUser,
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('role')->getData();
            $plainPassword = $form->get('plainPassword')->getData();
            $isActive = (bool) $form->get('isActive')->getData();

            // Don't let an admin lock themselves out of admin or disable
            // their own account from this screen - they can't recover from it.
            if ($this->isSameUser($currentUser, $user)) {
                $isActive = true;
                $role = UserRole::Admin;
            }

            try {
                $manager->update($user, is_string($plainPassword) ? $plainPassword : null, $role, $isActive);
                $this->addFlash('success', 'flash.user.updated');

                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            } catch (LastAdminException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/deactivate', name: 'app_user_deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deactivate(
        Request $request,
        User $user,
        UserAccountManager $manager,
        #[\Symfony\Component\Security\Http\Attribute\CurrentUser] UserInterface $currentUser,
    ): Response {
        if (!$this->isCsrfTokenValid('user-deactivate-' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        if ($this->isSameUser($currentUser, $user)) {
            $this->addFlash('error', 'user.error.cannot_deactivate_self');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        try {
            $manager->deactivate($user);
            $this->addFlash('success', 'flash.user.deactivated');
        } catch (LastAdminException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/activate', name: 'app_user_activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(Request $request, User $user, UserAccountManager $manager): Response
    {
        if (!$this->isCsrfTokenValid('user-activate-' . $user->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        $manager->activate($user);
        $this->addFlash('success', 'flash.user.activated');

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
    }

    private function isSameUser(UserInterface $currentUser, User $target): bool
    {
        return $currentUser instanceof User && $currentUser->getId() === $target->getId();
    }
}
