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

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_index')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/attributions', name: 'admin_attribution_index')]
    public function attributionIndex(AttributionRepository $attributionRepository): Response
    {
        $attributions = $attributionRepository->findAll();

        return $this->render('admin/attribution/index.html.twig', [
            'attributions' => $attributions,
        ]);
    }

    #[Route('/attributions/new', name: 'admin_attribution_new')]
    public function attributionNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $attribution = new Attribution();
        $form = $this->createForm(AttributionType::class, $attribution);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($attribution);
            $entityManager->flush();

            $this->addFlash('success', 'Attribution créée avec succès.');
            return $this->redirectToRoute('admin_attribution_index');
        }

        return $this->render('admin/attribution/new.html.twig', [
            'attribution' => $attribution,
            'form' => $form,
        ]);
    }

    #[Route('/attributions/{id}', name: 'admin_attribution_show')]
    public function attributionShow(Attribution $attribution): Response
    {
        return $this->render('admin/attribution/show.html.twig', [
            'attribution' => $attribution,
        ]);
    }

    #[Route('/attributions/{id}/edit', name: 'admin_attribution_edit')]
    public function attributionEdit(Request $request, Attribution $attribution, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AttributionType::class, $attribution);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Attribution modifiée avec succès.');
            return $this->redirectToRoute('admin_attribution_index');
        }

        return $this->render('admin/attribution/edit.html.twig', [
            'attribution' => $attribution,
            'form' => $form,
        ]);
    }

    #[Route('/attributions/{id}/delete', name: 'admin_attribution_delete', methods: ['POST'])]
    public function attributionDelete(Request $request, Attribution $attribution, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$attribution->getId(), $request->request->get('_token'))) {
            $entityManager->remove($attribution);
            $entityManager->flush();
            $this->addFlash('success', 'Attribution supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_attribution_index');
    }
}
