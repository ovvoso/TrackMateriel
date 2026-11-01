<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Identity\Entity\User;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Repository\AssetEventRepository;
use App\Inventory\Repository\AssetRepository;
use App\Inventory\Repository\LocationRepository;
use App\Lending\Repository\LoanRepository;
use App\Lending\Security\LoanVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USAGER')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] User $user,
        AssetRepository $assets,
        AssetEventRepository $events,
        LocationRepository $locations,
        LoanRepository $loans,
        ClockInterface $clock,
    ): Response {
        $byStatus = $assets->countByStatus();
        $today = $clock->now()->setTime(0, 0);

        // Retours attendus : tous pour le gestionnaire, les siens pour un emprunteur
        $borrower = $this->isGranted(LoanVoter::MANAGE) ? null : $user;

        return $this->render('dashboard/index.html.twig', [
            'by_status' => $byStatus,
            'active_total' => array_sum($byStatus) - $byStatus[AssetStatus::Retired->value],
            'by_category' => $assets->countByCategory(),
            'warranties' => $assets->findWarrantiesEndingBetween($today, $today->modify('+90 days')),
            'latest_events' => $events->findLatest(6),
            'location_count' => $locations->count(),
            'loan_counts' => $loans->countOpen($today),
            'returns_due' => $loans->findDueBefore($today->modify('+3 days'), 6, $borrower),
            'today' => $today,
            'statuses' => AssetStatus::cases(),
        ]);
    }
}
