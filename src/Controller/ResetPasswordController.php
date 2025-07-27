<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password', name: 'app_forgot_password')]
    public function request(): Response
    {
        return $this->render('reset_password/request.html.twig');
    }

    #[Route('/reset-password/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, string $token = null, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        // Pour la démonstration, on simule un token simple
        if ($token === 'demo-token') {
            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');
                $newPassword = $request->request->get('password');

                $user = $userRepository->findOneBy(['email' => $email]);
                if ($user) {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                    $entityManager->flush();

                    $this->addFlash('success', 'Mot de passe réinitialisé avec succès !');
                    return $this->redirectToRoute('app_login');
                }

                $this->addFlash('error', 'Utilisateur non trouvé.');
            }

            return $this->render('reset_password/reset.html.twig', [
                'token' => $token
            ]);
        }

        $this->addFlash('error', 'Token invalide.');
        return $this->redirectToRoute('app_forgot_password');
    }
}
