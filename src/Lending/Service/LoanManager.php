<?php

declare(strict_types=1);

namespace App\Lending\Service;

use App\Identity\Entity\User;
use App\Inventory\Entity\Asset;
use App\Inventory\Enum\AssetCondition;
use App\Inventory\Enum\AssetEventType;
use App\Inventory\Service\AssetHistory;
use App\Lending\Entity\Loan;
use App\Lending\Entity\LoanLine;
use App\Lending\Form\LoanInput;
use App\Lending\Form\ReturnLineInput;
use App\Lending\Model\Borrower;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Sortie et retour du matériel prêté. Chaque mouvement passe l'équipement
 * par le cycle de vie (Disponible ⇄ Prêté) et s'inscrit dans son journal.
 */
final readonly class LoanManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanReferenceGenerator $references,
        private AssetHistory $history,
        private Security $security,
        private ClockInterface $clock,
        #[Target('asset_lifecycle')]
        private WorkflowInterface $lifecycle,
    ) {
    }

    /**
     * @throws LendingException si un équipement n'est pas disponible
     */
    public function lend(LoanInput $input): Loan
    {
        \assert(null !== $input->dueAt);

        foreach ($input->assets as $asset) {
            if (!$this->lifecycle->can($asset, 'lend')) {
                throw new LendingException(\sprintf('%s (%s) n’est pas disponible : %s.', $asset->getName(), $asset->getCode(), mb_strtolower($asset->getStatus()->label())));
            }
        }

        $borrower = LoanInput::TO_ACCOUNT === $input->borrowerType && null !== $input->account
            ? Borrower::account($input->account)
            : Borrower::person((string) $input->personName, $input->personGroup);

        $loan = new Loan(
            $this->references->next(),
            $borrower,
            $this->clock->now(),
            $input->dueAt,
            $this->actor(),
            self::blankToNull($input->purpose),
            self::blankToNull($input->notes),
        );
        $this->entityManager->persist($loan);

        foreach ($input->assets as $asset) {
            new LoanLine($loan, $asset);
            $this->lifecycle->apply($asset, 'lend');
            $this->history->record($asset, AssetEventType::Lent, \sprintf(
                'Prêté à %s jusqu’au %s · %s',
                $borrower->describe(),
                self::formatDate($input->dueAt),
                $loan->getReference(),
            ));
        }

        $this->entityManager->flush();

        return $loan;
    }

    /**
     * Enregistre le retour des lignes cochées ; le prêt est clos au dernier retour.
     *
     * @param list<ReturnLineInput> $lines
     *
     * @return list<Asset> équipements rendus endommagés, à orienter vers la maintenance
     */
    public function receive(Loan $loan, array $lines): array
    {
        $now = $this->clock->now();
        $damaged = [];

        foreach ($lines as $input) {
            if (!$input->returned || $input->line->isReturned()) {
                continue;
            }

            $asset = $input->line->getAsset();
            $input->line->markReturned($now, $input->condition, $this->actor());
            $asset->setCondition($input->condition);
            $this->lifecycle->apply($asset, 'give_back');
            $this->history->record($asset, AssetEventType::Returned, \sprintf(
                'Rendu par %s · état : %s · %s',
                $loan->getBorrower()->describe(),
                mb_strtolower($input->condition->label()),
                $loan->getReference(),
            ));

            if (AssetCondition::Damaged === $input->condition) {
                $damaged[] = $asset;
            }
        }

        $loan->closeIfComplete($now);
        $this->entityManager->flush();

        return $damaged;
    }

    public function extend(Loan $loan, \DateTimeImmutable $dueAt): void
    {
        $loan->extendTo($dueAt);
        $this->entityManager->flush();
    }

    private function actor(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    private static function formatDate(\DateTimeInterface $date): string
    {
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);

        return (string) $formatter->format($date);
    }
}
