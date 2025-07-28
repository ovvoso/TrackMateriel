<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password', name: 'app_forgot_password')]
    public function request(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            if ($email) {
                $user = $userRepository->findOneBy(['email' => $email]);
                
                if ($user) {
                    // Générer un token unique
                    $token = bin2hex(random_bytes(32));
                    $user->setResetToken($token);
                    $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                    
                    $entityManager->flush();
                    
                    // Générer l'URL de réinitialisation
                    $resetUrl = $this->generateUrl('app_reset_password', 
                        ['token' => $token], 
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );
                    
                    // Pour la démonstration, on affiche le lien au lieu d'envoyer un email
                    $this->addFlash('success', 
                        'Un lien de réinitialisation a été généré. ' .
                        'Pour la démonstration, voici le lien : ' . $resetUrl
                    );
                    
                    // Code pour envoyer un email (commenté pour la démo)
                    /*
                    try {
                        $email = (new Email())
                            ->from('noreply@trackmateriel.com')
                            ->to($user->getEmail())
                            ->subject('Réinitialisation de votre mot de passe')
                            ->html($this->renderView('reset_password/email.html.twig', [
                                'user' => $user,
                                'resetUrl' => $resetUrl
                            ]));
                        
                        $mailer->send($email);
                        $this->addFlash('success', 'Un email de réinitialisation a été envoyé à votre adresse.');
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email.');
                    }
                    */
                } else {
                    // Pour des raisons de sécurité, on n'indique pas si l'email existe ou non
                    $this->addFlash('info', 'Si cette adresse email existe, un lien de réinitialisation vous sera envoyé.');
                }
                
                return $this->redirectToRoute('app_check_email');
            }
        }
        
        return $this->render('reset_password/request.html.twig');
    }

    #[Route('/reset-password/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, string $token, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        // Trouver l'utilisateur avec ce token
        $user = $userRepository->findOneBy(['resetToken' => $token]);
        
        if (!$user || !$user->isResetTokenValid()) {
            $this->addFlash('error', 'Token invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            
            if (!$newPassword || strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } else {
                // Réinitialiser le mot de passe
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                
                $entityManager->flush();
                
                $this->addFlash('success', 'Mot de passe réinitialisé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }
        
        return $this->render('reset_password/reset.html.twig', [
            'token' => $token,
            'user' => $user
        ]);
    }
}
