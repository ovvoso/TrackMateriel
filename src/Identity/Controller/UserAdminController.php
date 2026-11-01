<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use App\Identity\Form\InviteInput;
use App\Identity\Form\InviteType;
use App\Identity\Form\UserEditType;
use App\Identity\Repository\AccountTokenRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountException;
use App\Identity\Service\AccountManager;
use App\Lending\Repository\LoanRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    public function __construct(
        private readonly AccountManager $accounts,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $role = Role::tryFrom($request->query->getString('profil'));
        $status = $request->query->getString('statut');

        return $this->render('identity/admin/index.html.twig', [
            'page' => $this->users->search('' === $query ? null : $query, $role, '' === $status ? null : $status, max(1, $request->query->getInt('page', 1))),
            'query' => $query,
            'role' => $role,
            'status' => $status,
            'roles' => Role::cases(),
        ]);
    }

    #[Route('/inviter', name: 'app_user_invite', methods: ['GET', 'POST'])]
    public function invite(Request $request, #[CurrentUser] User $admin): Response
    {
        $input = new InviteInput();
        $form = $this->createForm(InviteType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $this->accounts->invite($input, $admin);
                $this->addFlash('success', \sprintf('Invitation envoyée à %s.', $user->getEmail()));
            } catch (TransportExceptionInterface) {
                $this->addFlash('danger', 'Le compte est créé mais l’e-mail n’a pas pu partir. Réessayez l’envoi depuis sa fiche.');
            }

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('identity/admin/invite.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_user_edit', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID])]
    public function edit(
        Request $request,
        User $user,
        #[CurrentUser] User $admin,
        LoanRepository $loans,
        AccountTokenRepository $tokens,
        ClockInterface $clock,
    ): Response {
        $isSelf = $user->getId()->equals($admin->getId());
        $form = $this->createForm(UserEditType::class, [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'role' => $user->getRole(),
        ], ['lock_role' => $isSelf]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{firstName: string, lastName: string, role: Role|null} $data */
            $data = $form->getData();
            try {
                $this->accounts->update($user, $data['firstName'], $data['lastName'], $data['role'] ?? $user->getRole(), $admin);
                $this->addFlash('success', 'Compte enregistré.');

                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            } catch (AccountException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        $today = $clock->now()->setTime(0, 0);

        return $this->render('identity/admin/edit.html.twig', [
            'form' => $form,
            'user' => $user,
            'is_self' => $isSelf,
            'open_loans' => $loans->countOpen($today, $user),
            'invitation_pending' => !$user->isActivated() && $tokens->hasOpenInvitation($user, $clock->now()),
        ]);
    }

    #[Route('/{id}/invitation', name: 'app_user_resend', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsCsrfTokenValid(new Expression('"user-invite-" ~ args["user"].getId()'))]
    public function resend(User $user, #[CurrentUser] User $admin): Response
    {
        try {
            $this->accounts->sendInvitation($user, $admin);
            $this->addFlash('success', \sprintf('Nouvelle invitation envoyée à %s ; les liens précédents ne fonctionnent plus.', $user->getEmail()));
        } catch (AccountException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        } catch (TransportExceptionInterface) {
            $this->addFlash('danger', 'L’e-mail n’a pas pu partir. Vérifiez la configuration d’envoi et réessayez.');
        }

        return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/desactiver', name: 'app_user_deactivate', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsCsrfTokenValid(new Expression('"user-status-" ~ args["user"].getId()'))]
    public function deactivate(User $user, #[CurrentUser] User $admin): Response
    {
        try {
            $this->accounts->deactivate($user, $admin);
            $this->addFlash('success', \sprintf('Le compte de %s est désactivé : il ne peut plus se connecter.', $user->getFullName()));
        } catch (AccountException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reactiver', name: 'app_user_reactivate', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsCsrfTokenValid(new Expression('"user-status-" ~ args["user"].getId()'))]
    public function reactivate(User $user): Response
    {
        $this->accounts->reactivate($user);
        $this->addFlash('success', \sprintf('Le compte de %s est réactivé.', $user->getFullName()));

        return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }
}
