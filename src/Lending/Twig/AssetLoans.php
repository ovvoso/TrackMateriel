<?php

declare(strict_types=1);

namespace App\Lending\Twig;

use App\Inventory\Entity\Asset;
use App\Lending\Entity\Loan;
use App\Lending\Repository\LoanRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Encart « Prêts » de la fiche équipement : prêt en cours et derniers emprunts.
 * Le domaine Inventaire l'affiche sans dépendre du domaine Prêts.
 */
#[AsTwigComponent('AssetLoans', template: 'lending/_asset_loans.html.twig')]
final class AssetLoans
{
    public Asset $asset;

    public function __construct(private readonly LoanRepository $loans)
    {
    }

    public function getCurrent(): ?Loan
    {
        return $this->loans->findOpenForAsset($this->asset);
    }

    /**
     * @return list<Loan>
     */
    public function getHistory(): array
    {
        return $this->loans->findForAsset($this->asset, 5);
    }
}
