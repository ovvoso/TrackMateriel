<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEventListener(event: 'kernel.exception')]
class AccessDeniedListener
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedHttpException) {
            return;
        }

        // Ajouter un message flash
        $this->requestStack->getSession()->getFlashBag()->add('error', 'Accès refusé : Vous n\'avez pas les autorisations nécessaires pour effectuer cette action.');

        // Rediriger vers la page d'accueil
        $response = new RedirectResponse($this->router->generate('app_home'));
        $event->setResponse($response);
    }
}
