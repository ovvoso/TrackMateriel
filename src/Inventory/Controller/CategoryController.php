<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Inventory\Entity\Category;
use App\Inventory\Form\CategoryType;
use App\Inventory\Repository\CategoryRepository;
use App\Inventory\Security\InventoryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/categories')]
#[IsGranted(InventoryVoter::MANAGE)]
final class CategoryController extends AbstractController
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    #[Route('', name: 'app_category_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('inventory/category/index.html.twig', [
            'rows' => $this->categories->findAllWithAssetCount(),
        ]);
    }

    #[Route('/nouvelle', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(CategoryType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $form->getData();
            \assert($category instanceof Category);
            $this->categories->save($category);
            $this->addFlash('success', \sprintf('Catégorie « %s » créée.', $category->getName()));

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/category/form.html.twig', ['form' => $form, 'category' => null]);
    }

    #[Route('/{id}/modifier', name: 'app_category_edit', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID])]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categories->save($category);
            $this->addFlash('success', 'Catégorie enregistrée.');

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/category/form.html.twig', ['form' => $form, 'category' => $category]);
    }

    #[Route('/{id}/supprimer', name: 'app_category_delete', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsCsrfTokenValid(new Expression('"category-delete-" ~ args["category"].getId()'))]
    public function delete(Category $category): Response
    {
        if ($this->categories->isInUse($category)) {
            $this->addFlash('danger', \sprintf('La catégorie « %s » est utilisée par des équipements : elle ne peut pas être supprimée.', $category->getName()));
        } else {
            $this->categories->remove($category);
            $this->addFlash('success', \sprintf('Catégorie « %s » supprimée.', $category->getName()));
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
