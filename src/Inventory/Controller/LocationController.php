<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Inventory\Entity\Location;
use App\Inventory\Enum\LocationKind;
use App\Inventory\Form\LocationType;
use App\Inventory\Repository\LocationRepository;
use App\Inventory\Security\InventoryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/emplacements')]
#[IsGranted('ROLE_USAGER')]
final class LocationController extends AbstractController
{
    public function __construct(private readonly LocationRepository $locations)
    {
    }

    #[Route('', name: 'app_location_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('inventory/location/index.html.twig', [
            'sites' => $this->locations->findTree(),
            'asset_counts' => $this->locations->countAssetsPerLocation(),
        ]);
    }

    #[Route('/nouveau', name: 'app_location_new', methods: ['GET', 'POST'])]
    #[IsGranted(InventoryVoter::MANAGE)]
    public function new(Request $request): Response
    {
        // Création depuis un bâtiment ou un site : le type et le rattachement sont préremplis
        $parent = Uuid::isValid($request->query->getString('dans')) ? $this->locations->find($request->query->getString('dans')) : null;
        $location = null;
        if (null !== $parent && LocationKind::Room !== $parent->getKind()) {
            $location = new Location('', LocationKind::Site === $parent->getKind() ? LocationKind::Building : LocationKind::Room, $parent);
        }

        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $created = $form->getData();
            \assert($created instanceof Location);
            $this->locations->save($created);
            $this->addFlash('success', \sprintf('%s « %s » créé.', $created->getKind()->label(), $created->getName()));

            return $this->redirectToRoute('app_location_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/location/form.html.twig', ['form' => $form, 'location' => null]);
    }

    #[Route('/{id}/modifier', name: 'app_location_edit', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted(InventoryVoter::MANAGE)]
    public function edit(Request $request, Location $location): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->locations->save($location);
            $this->addFlash('success', 'Emplacement enregistré.');

            return $this->redirectToRoute('app_location_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/location/form.html.twig', ['form' => $form, 'location' => $location]);
    }

    #[Route('/{id}/supprimer', name: 'app_location_delete', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted(InventoryVoter::MANAGE)]
    #[IsCsrfTokenValid(new Expression('"location-delete-" ~ args["location"].getId()'))]
    public function delete(Location $location): Response
    {
        if ($this->locations->isInUse($location)) {
            $this->addFlash('danger', \sprintf('« %s » contient des emplacements ou du matériel : videz-le avant de le supprimer.', $location->getName()));
        } else {
            $this->locations->remove($location);
            $this->addFlash('success', \sprintf('« %s » supprimé.', $location->getName()));
        }

        return $this->redirectToRoute('app_location_index', [], Response::HTTP_SEE_OTHER);
    }
}
