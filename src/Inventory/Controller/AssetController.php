<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Form\AssetInput;
use App\Inventory\Form\AssetType;
use App\Inventory\Repository\AssetEventRepository;
use App\Inventory\Repository\AssetRepository;
use App\Inventory\Repository\AssetSearch;
use App\Inventory\Repository\CategoryRepository;
use App\Inventory\Repository\LocationRepository;
use App\Inventory\Security\InventoryVoter;
use App\Inventory\Service\AssetManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Exception\TransitionException;

#[Route('/inventaire')]
#[IsGranted('ROLE_USAGER')]
final class AssetController extends AbstractController
{
    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetManager $manager,
    ) {
    }

    #[Route('', name: 'app_asset_index', methods: ['GET'])]
    public function index(Request $request, CategoryRepository $categories, LocationRepository $locations): Response
    {
        $search = AssetSearch::fromRequest($request);

        return $this->render('inventory/asset/index.html.twig', [
            'search' => $search,
            'page' => $this->assets->search($search),
            'categories' => $categories->findBy([], ['name' => 'ASC']),
            'locations' => $locations->findAllOrderedByPath(),
            'statuses' => AssetStatus::cases(),
        ]);
    }

    /**
     * Export CSV de la recherche en cours (séparateur « ; » et BOM pour Excel).
     */
    #[Route('/export.csv', name: 'app_asset_export', methods: ['GET'])]
    #[IsGranted(InventoryVoter::MANAGE)]
    public function export(Request $request): StreamedResponse
    {
        $assets = $this->assets->searchAll(AssetSearch::fromRequest($request));

        $response = new StreamedResponse(static function () use ($assets): void {
            $out = fopen('php://output', 'w');
            \assert(false !== $out);
            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['Code', 'Désignation', 'Catégorie', 'Marque', 'Modèle', 'Numéro de série', 'État', 'État physique', 'Emplacement', 'Date d’achat', 'Prix d’achat', 'Fin de garantie', 'Fournisseur'], ';', escape: '');
            foreach ($assets as $asset) {
                fputcsv($out, [
                    $asset->getCode(),
                    $asset->getName(),
                    $asset->getCategory()->getName(),
                    $asset->getBrand(),
                    $asset->getModel(),
                    $asset->getSerialNumber(),
                    $asset->getStatus()->label(),
                    $asset->getCondition()->label(),
                    $asset->getLocation()?->getPath(),
                    $asset->getPurchasedAt()?->format('d/m/Y'),
                    $asset->getPurchasePrice(),
                    $asset->getWarrantyEndsAt()?->format('d/m/Y'),
                    $asset->getSupplier(),
                ], ';', escape: '');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="inventaire-'.date('Y-m-d').'.csv"');

        return $response;
    }

    #[Route('/nouveau', name: 'app_asset_new', methods: ['GET', 'POST'])]
    #[IsGranted(InventoryVoter::MANAGE)]
    public function new(Request $request): Response
    {
        $input = new AssetInput();
        $form = $this->createForm(AssetType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $asset = $this->manager->create($input);
            $this->addFlash('success', \sprintf('Équipement %s ajouté à l’inventaire.', $asset->getCode()));

            return $this->redirectToRoute('app_asset_show', ['code' => $asset->getCode()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/asset/form.html.twig', ['form' => $form, 'asset' => null]);
    }

    #[Route('/{code}', name: 'app_asset_show', methods: ['GET'], requirements: ['code' => 'INV-\d{4}-\d{5,}'])]
    public function show(#[MapEntity(mapping: ['code' => 'code'])] Asset $asset, AssetEventRepository $events): Response
    {
        return $this->render('inventory/asset/show.html.twig', [
            'asset' => $asset,
            'events' => $events->findForAsset($asset),
        ]);
    }

    #[Route('/{code}/modifier', name: 'app_asset_edit', methods: ['GET', 'POST'], requirements: ['code' => 'INV-\d{4}-\d{5,}'])]
    #[IsGranted(InventoryVoter::EDIT, subject: 'asset')]
    public function edit(Request $request, #[MapEntity(mapping: ['code' => 'code'])] Asset $asset): Response
    {
        $input = AssetInput::fromAsset($asset);
        $form = $this->createForm(AssetType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->update($asset, $input);
            $this->addFlash('success', 'Fiche enregistrée.');

            return $this->redirectToRoute('app_asset_show', ['code' => $asset->getCode()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/asset/form.html.twig', ['form' => $form, 'asset' => $asset]);
    }

    #[Route('/{code}/etat/{transition}', name: 'app_asset_transition', methods: ['POST'], requirements: ['code' => 'INV-\d{4}-\d{5,}', 'transition' => '[a-z_]+'])]
    #[IsCsrfTokenValid(new Expression('"asset-transition-" ~ args["asset"].getCode()'))]
    public function transition(#[MapEntity(mapping: ['code' => 'code'])] Asset $asset, string $transition): Response
    {
        try {
            $this->manager->apply($asset, $transition);
            $this->addFlash('success', \sprintf('Nouvel état : %s.', $asset->getStatus()->label()));
        } catch (TransitionException) {
            throw $this->createAccessDeniedException('Ce changement d’état n’est pas permis.');
        }

        return $this->redirectToRoute('app_asset_show', ['code' => $asset->getCode()], Response::HTTP_SEE_OTHER);
    }
}
