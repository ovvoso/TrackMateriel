<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Form\AttributionType;
use App\Repository\AttributionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/attribution')]
final class AttributionController extends AbstractController
{
    #[Route(name: 'app_attribution_index', methods: ['GET'])]
    public function index(AttributionRepository $attributionRepository): Response
    {
        return $this->render('attribution/index.html.twig', [
            'attributions' => $attributionRepository->findAllWithRelations(),
        ]);
    }

    #[Route('/new', name: 'app_attribution_new', methods: ['GET', 'POST'])]
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
    public function show(int $id, AttributionRepository $attributionRepository): Response
    {
        $attribution = $attributionRepository->findWithRelations($id);
        
        if (!$attribution) {
            $this->addFlash('error', 'L\'attribution avec l\'ID ' . $id . ' n\'existe pas ou a été supprimée.');
            return $this->redirectToRoute('app_attribution_index');
        }

        return $this->render('attribution/show.html.twig', [
            'attribution' => $attribution,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_attribution_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
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
}
