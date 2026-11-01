<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Form\ChangePasswordType;
use App\Identity\Service\AccountManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USAGER')]
final class ProfileController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_profile', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        AccountManager $accounts,
        Security $security,
        #[Autowire('%app.demo%')]
        bool $demo,
    ): Response {
        // Sur l'instance de démonstration, les comptes sont partagés : leur mot de passe ne change pas
        if ($demo) {
            return $this->render('identity/profile.html.twig', ['form' => null]);
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            \assert(\is_string($password));
            $accounts->changePassword($user, $password);
            // Le changement de mot de passe invalide toutes les sessions, y compris celle-ci : on la rouvre
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Mot de passe modifié. Les sessions « Rester connecté » ouvertes sur d’autres appareils sont fermées.');

            return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('identity/profile.html.twig', ['form' => $form]);
    }
}
