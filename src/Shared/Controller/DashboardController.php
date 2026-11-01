<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Inventory\Enum\AssetStatus;
use App\Inventory\Repository\AssetEventRepository;
use App\Inventory\Repository\AssetRepository;
use App\Inventory\Repository\LocationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USAGER')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(
        AssetRepository $assets,
        AssetEventRepository $events,
        LocationRepository $locations,
        ClockInterface $clock,
    ): Response {
        $byStatus = $assets->countByStatus();
        $today = $clock->now()->setTime(0, 0);

        return $this->render('dashboard/index.html.twig', [
            'by_status' => $byStatus,
            'active_total' => array_sum($byStatus) - $byStatus[AssetStatus::Retired->value],
            'by_category' => $assets->countByCategory(),
            'warranties' => $assets->findWarrantiesEndingBetween($today, $today->modify('+90 days')),
            'latest_events' => $events->findLatest(6),
            'location_count' => $locations->count(),
            'statuses' => AssetStatus::cases(),
        ]);
    }
}
