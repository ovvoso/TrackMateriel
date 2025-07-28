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
        
        if (!$user) {
            throw $this->createAccessDeniedException('User not authenticated');
        }

        // Use QueryBuilder for better control and to ensure entities are properly loaded
        $attributions = $attributionRepository->createQueryBuilder('a')
            ->leftJoin('a.equipement', 'e')
            ->leftJoin('a.utilisateur', 'u')
            ->addSelect('e', 'u')
            ->where('a.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('a.dateAttribution', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('user/attributions.html.twig', [
            'attributions' => $attributions,
        ]);
    }
}
