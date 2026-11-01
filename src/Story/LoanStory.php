<?php

declare(strict_types=1);

namespace App\Story;

use App\Identity\Entity\User;
use App\Identity\Factory\UserFactory;
use App\Inventory\Entity\Asset;
use App\Inventory\Entity\AssetEvent;
use App\Inventory\Enum\AssetCondition;
use App\Inventory\Enum\AssetEventType;
use App\Inventory\Enum\AssetStatus;
use App\Inventory\Factory\AssetFactory;
use App\Lending\Entity\Loan;
use App\Lending\Entity\LoanLine;
use App\Lending\Model\Borrower;
use App\Lending\Service\LoanReferenceGenerator;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\Persistence\flush_after;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Prêts de démonstration : en cours, en retard, rendus, à des enseignants
 * (comptes) comme à des élèves (sans compte).
 */
final class LoanStory extends Story
{
    /** @var array<string, list<Asset>> équipements disponibles par modèle */
    private array $pool = [];

    public function __construct(private readonly LoanReferenceGenerator $references)
    {
    }

    public function build(): void
    {
        $manager = UserFactory::find(['email' => AppStory::MANAGER_EMAIL]);
        $teacher = UserFactory::find(['email' => AppStory::TEACHER_EMAIL]);
        $biology = UserFactory::find(['email' => 'mariama.ba@lycee-demo.sn']);
        $computing = UserFactory::find(['email' => 'ousmane.diallo@lycee-demo.sn']);

        // Matériel en état de circuler ; celui en service d'abord, pour laisser du disponible au formulaire
        foreach (AssetFactory::repository()->findBy(['status' => [AssetStatus::InService, AssetStatus::Available]], ['status' => 'DESC', 'code' => 'ASC']) as $asset) {
            $this->pool[(string) $asset->getModel()][] = $asset;
        }

        flush_after(function () use ($manager, $teacher, $biology, $computing): void {
            // Prêts clos (historique)
            $this->loan(Borrower::account($teacher), ['iPad 10e génération' => 4], '-45 days', '-38 days', 'Lecture numérique en classe de 2nde', $manager, returnedAfter: 6);
            $this->loan(Borrower::person('Awa Ndiaye', 'Terminale S1'), ['EOS 2000D' => 1], '-30 days', '-27 days', 'Exposé de géographie', $manager, returnedAfter: 3);
            $this->loan(Borrower::account($computing), ['SPIKE Prime' => 2], '-20 days', '-13 days', 'Préparation du concours de robotique', $manager, returnedAfter: 7);

            // Prêts en retard
            $this->loan(Borrower::account($biology), ['Biolux NV' => 3], '-12 days', '-2 days', 'TP d’observation cellulaire', $manager);
            $this->loan(Borrower::person('Cheikh Ndiaye', '1re L2'), ['Latitude 3440' => 1], '-15 days', '-6 days', 'Rédaction du mémoire de TPE', $manager);

            // Prêts en cours
            $this->loan(Borrower::account($teacher), ['MW560' => 1], '-5 days', '+2 days', 'Cours d’histoire en salle B14', $manager);
            $this->loan(Borrower::person('Aminata Sow', 'Terminale S1'), ['EOS 2000D' => 1], '-3 days', 'today', 'Reportage du journal du lycée', $manager);
            $this->loan(Borrower::account($computing), ['SPIKE Prime' => 2, 'Starter Kit' => 2], '-2 days', '+12 days', 'Atelier du club robotique', $manager);
        });
    }

    /**
     * @param array<string, int> $models modèle => quantité à prêter
     */
    private function loan(Borrower $borrower, array $models, string $lent, string $due, string $purpose, User $manager, ?int $returnedAfter = null): void
    {
        $lentAt = (new \DateTimeImmutable($lent))->setTime(10, 15);
        $dueAt = (new \DateTimeImmutable($due))->setTime(0, 0);

        $loan = new Loan($this->references->next(), $borrower, $lentAt, $dueAt, $manager, $purpose);
        save($loan);

        foreach ($models as $model => $quantity) {
            for ($i = 0; $i < $quantity; ++$i) {
                $asset = array_shift($this->pool[$model]);
                \assert($asset instanceof Asset, \sprintf('Plus d’équipement « %s » disponible pour la démo.', $model));

                $line = new LoanLine($loan, $asset);
                save($line);
                $this->log($asset, AssetEventType::Lent, \sprintf('Prêté à %s jusqu’au %s · %s', $borrower->describe(), $dueAt->format('d/m/Y'), $loan->getReference()), $manager, $lentAt);

                if (null === $returnedAfter) {
                    $asset->setStatus(AssetStatus::OnLoan);
                    continue;
                }

                $returnedAt = $lentAt->modify(\sprintf('+%d days', $returnedAfter))->setTime(16, 40);
                $line->markReturned($returnedAt, AssetCondition::Good, $manager);
                $this->log($asset, AssetEventType::Returned, \sprintf('Rendu par %s · état : bon état · %s', $borrower->describe(), $loan->getReference()), $manager, $returnedAt);
                // Rendu, il redevient prêtable pour les prêts suivants de la démo
                $this->pool[$model][] = $asset;
            }
        }

        if (null !== $returnedAfter) {
            $loan->closeIfComplete($lentAt->modify(\sprintf('+%d days', $returnedAfter))->setTime(16, 40));
        }
    }

    private function log(Asset $asset, AssetEventType $type, string $summary, User $actor, \DateTimeImmutable $at): void
    {
        save(new AssetEvent($asset, $type, $summary, $actor, $at));
        if ($at > $asset->getUpdatedAt()) {
            $asset->touch($at);
        }
    }
}
