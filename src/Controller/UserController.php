<?php

namespace App\Controller;

use App\Repository\AttributionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/', name: 'user_index')]
    public function index(): Response
    {
        return $this->render('user/index.html.twig');
    }

    #[Route('/mes-attributions', name: 'user_attributions')]
    public function mesAttributions(AttributionRepository $attributionRepository): Response
    {
        $user = $this->getUser();
        $attributions = $attributionRepository->findBy(['utilisateur' => $user]);

        return $this->render('user/attributions.html.twig', [
            'attributions' => $attributions,
        ]);
    }
}
