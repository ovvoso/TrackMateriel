<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Form\AttributionType;
use App\Repository\AttributionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/attribution')]
final class AttributionController extends AbstractController
{
    #[Route(name: 'app_attribution_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(AttributionRepository $attributionRepository): Response
    {
        // Si l'utilisateur est un admin, il voit toutes les attributions
        if ($this->isGranted('ROLE_ADMIN')) {
            $attributions = $attributionRepository->findAllWithRelations();
        } else {
            // Si c'est un utilisateur normal, il ne voit que ses propres attributions
            $attributions = $attributionRepository->findByUserWithRelations($this->getUser());
        }

        return $this->render('attribution/index.html.twig', [
            'attributions' => $attributions,
        ]);
    }

    #[Route('/new', name: 'app_attribution_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $attribution = new Attribution();
        $form = $this->createForm(AttributionType::class, $attribution);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($attribution);
            $entityManager->flush();

            $this->addFlash('success', 'Attribution créée avec succès.');
            return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('attribution/new.html.twig', [
            'attribution' => $attribution,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_attribution_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(int $id, AttributionRepository $attributionRepository): Response
    {
        $attribution = $attributionRepository->findWithRelations($id);
        
        if (!$attribution) {
            $this->addFlash('error', 'L\'attribution avec l\'ID ' . $id . ' n\'existe pas ou a été supprimée.');
            return $this->redirectToRoute('app_attribution_index');
        }

        // Vérifier que l'utilisateur peut voir cette attribution
        if (!$this->canAccessAttribution($attribution)) {
            $this->addFlash('warning', 'Vous ne pouvez consulter que vos propres attributions.');
            return $this->redirectToRoute('app_attribution_index');
        }

        return $this->render('attribution/show.html.twig', [
            'attribution' => $attribution,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_attribution_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, int $id, AttributionRepository $attributionRepository, EntityManagerInterface $entityManager): Response
    {
        $attribution = $attributionRepository->findWithRelations($id);
        
        if (!$attribution) {
            $this->addFlash('error', 'L\'attribution avec l\'ID ' . $id . ' n\'existe pas ou a été supprimée.');
            return $this->redirectToRoute('app_attribution_index');
        }

        $form = $this->createForm(AttributionType::class, $attribution);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Attribution modifiée avec succès.');
            return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('attribution/edit.html.twig', [
            'attribution' => $attribution,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_attribution_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, int $id, AttributionRepository $attributionRepository, EntityManagerInterface $entityManager): Response
    {
        $attribution = $attributionRepository->find($id);
        
        if (!$attribution) {
            $this->addFlash('error', 'L\'attribution avec l\'ID ' . $id . ' n\'existe pas ou a été supprimée.');
            return $this->redirectToRoute('app_attribution_index');
        }

        if ($this->isCsrfTokenValid('delete'.$attribution->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($attribution);
            $entityManager->flush();
            
            $this->addFlash('success', 'Attribution supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_attribution_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Vérifie si l'utilisateur connecté peut accéder à cette attribution
     */
    private function canAccessAttribution(Attribution $attribution): bool
    {
        // Les admins peuvent tout voir
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Les utilisateurs ne peuvent voir que leurs propres attributions
        return $attribution->getUtilisateur() === $this->getUser();
    }
}
