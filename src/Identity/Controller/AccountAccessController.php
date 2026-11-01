<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Enum\TokenPurpose;
use App\Identity\Form\NewPasswordType;
use App\Identity\Service\AccountManager;
use App\Identity\Service\AccountTokens;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Accès au compte sans être connecté : activation depuis une invitation,
 * mot de passe oublié. Les liens sont à usage unique et à durée limitée.
 */
final class AccountAccessController extends AbstractController
{
    public function __construct(
        private readonly AccountManager $accounts,
        private readonly AccountTokens $tokens,
    ) {
    }

    #[Route('/compte/activer/{id}/{secret}', name: 'app_account_activate', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID, 'secret' => '[a-f0-9]{48}'])]
    public function activate(Request $request, string $id, string $secret): Response
    {
        $token = $this->tokens->find(TokenPurpose::Invitation, $id, $secret);
        if (null === $token || !$token->getUser()->isActive()) {
            return $this->render('identity/link_invalid.html.twig', ['purpose' => 'invitation'], new Response(null, Response::HTTP_GONE));
        }

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            \assert(\is_string($password));
            $user = $this->accounts->activate($token, $password);
            $this->addFlash('success', 'Votre compte est activé. Connectez-vous avec votre nouveau mot de passe.');

            return $this->redirectToRoute('app_login', ['email' => $user->getEmail()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('identity/activate.html.twig', ['form' => $form, 'user' => $token->getUser()]);
    }

    #[Route('/mot-de-passe-oublie', name: 'app_password_forgotten', methods: ['GET', 'POST'])]
    public function forgotten(
        Request $request,
        #[Target('password_reset.limiter')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        $form = $this->createFormBuilder(null, ['translation_domain' => false])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez votre adresse e-mail.'), new Assert\Email()],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            \assert(\is_string($email));

            // Limité par adresse et par machine ; au-delà, on répond pareil sans rien envoyer
            $byEmail = $limiter->create('email-'.mb_strtolower($email))->consume();
            $byClient = $limiter->create('ip-'.$request->getClientIp())->consume();
            if ($byEmail->isAccepted() && $byClient->isAccepted()) {
                $this->accounts->requestPasswordReset($email);
            }

            return $this->redirectToRoute('app_password_forgotten_sent', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('identity/forgotten.html.twig', ['form' => $form]);
    }

    #[Route('/mot-de-passe-oublie/envoye', name: 'app_password_forgotten_sent', methods: ['GET'])]
    public function forgottenSent(): Response
    {
        return $this->render('identity/forgotten_sent.html.twig');
    }

    #[Route('/compte/reinitialiser/{id}/{secret}', name: 'app_password_reset', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID, 'secret' => '[a-f0-9]{48}'])]
    public function reset(Request $request, string $id, string $secret): Response
    {
        $token = $this->tokens->find(TokenPurpose::PasswordReset, $id, $secret);
        if (null === $token || !$token->getUser()->isActive()) {
            return $this->render('identity/link_invalid.html.twig', ['purpose' => 'reset'], new Response(null, Response::HTTP_GONE));
        }

        $form = $this->createForm(NewPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $form->get('password')->getData();
            \assert(\is_string($password));
            $this->accounts->resetPassword($token, $password);
            $this->addFlash('success', 'Mot de passe modifié. Connectez-vous avec le nouveau.');

            return $this->redirectToRoute('app_login', ['email' => $token->getUser()->getEmail()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('identity/reset.html.twig', ['form' => $form, 'user' => $token->getUser()]);
    }
}
