<?php

declare(strict_types=1);

namespace App\Lending\Repository;

use App\Identity\Entity\User;
use App\Inventory\Entity\Asset;
use App\Lending\Entity\Loan;
use App\Lending\Entity\LoanLine;
use App\Lending\Enum\LoanState;
use App\Shared\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public const int PER_PAGE = 20;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * @return Page<Loan>
     */
    public function search(LoanSearch $search, \DateTimeImmutable $today): Page
    {
        $qb = $this->createQueryBuilder('l')
            ->addSelect('line', 'a')
            ->join('l.lines', 'line')
            ->join('line.asset', 'a');

        match ($search->state) {
            LoanState::Overdue => $qb->where('l.closedAt IS NULL AND l.dueAt < :today')->setParameter('today', $today),
            LoanState::Returned => $qb->where('l.closedAt IS NOT NULL'),
            default => $qb->where('l.closedAt IS NULL'),
        };

        if (null !== $search->query) {
            // Un prêt correspond s'il contient au moins un équipement cherché, ou si l'emprunteur correspond
            $matching = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(LoanLine::class, 'ml')
                ->join('ml.asset', 'ma')
                ->where('ml.loan = l AND (LOWER(ma.code) LIKE :q OR LOWER(ma.name) LIKE :q)');

            $qb->andWhere($qb->expr()->orX(
                'LOWER(l.reference) LIKE :q',
                'LOWER(l.borrowerName) LIKE :q',
                'LOWER(l.borrowerGroup) LIKE :q',
                $qb->expr()->exists($matching->getDQL()),
            ))->setParameter('q', '%'.addcslashes(mb_strtolower($search->query), '%_').'%');
        }

        if (null !== $search->borrower) {
            $qb->andWhere('l.borrowerAccount = :borrower')->setParameter('borrower', $search->borrower);
        }

        $qb->orderBy(LoanState::Returned === $search->state ? 'l.closedAt' : 'l.dueAt', LoanState::Returned === $search->state ? 'DESC' : 'ASC')
            ->addOrderBy('l.reference', 'DESC');

        /** @var Page<Loan> $page */
        $page = Page::fromQuery($qb, $search->page, self::PER_PAGE);

        return $page;
    }

    /**
     * @return array{ongoing: int, overdue: int}
     */
    public function countOpen(\DateTimeImmutable $today, ?User $borrower = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) AS ongoing, SUM(CASE WHEN l.dueAt < :today THEN 1 ELSE 0 END) AS overdue')
            ->where('l.closedAt IS NULL')
            ->setParameter('today', $today);
        $this->restrictTo($qb, $borrower);

        /** @var array{ongoing: int|string, overdue: int|string|null} $row */
        $row = $qb->getQuery()->getSingleResult();

        return ['ongoing' => (int) $row['ongoing'], 'overdue' => (int) $row['overdue']];
    }

    /**
     * Prêts ouverts à rendre avant la date donnée (retards compris), les plus urgents d'abord.
     *
     * @return list<Loan>
     */
    public function findDueBefore(\DateTimeImmutable $until, int $limit, ?User $borrower = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.closedAt IS NULL')
            ->andWhere('l.dueAt <= :until')
            ->setParameter('until', $until)
            ->orderBy('l.dueAt', 'ASC')
            ->setMaxResults($limit);
        $this->restrictTo($qb, $borrower);

        /** @var list<Loan> $loans */
        $loans = $qb->getQuery()->getResult();

        return $loans;
    }

    /**
     * Le prêt ouvert dans lequel se trouve un équipement, s'il n'est pas encore rendu.
     */
    public function findOpenForAsset(Asset $asset): ?Loan
    {
        /** @var Loan|null $loan */
        $loan = $this->createQueryBuilder('l')
            ->join('l.lines', 'line')
            ->where('line.asset = :asset AND line.returnedAt IS NULL')
            ->setParameter('asset', $asset)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return $loan;
    }

    /**
     * Historique des prêts d'un équipement, du plus récent au plus ancien.
     *
     * @return list<Loan>
     */
    public function findForAsset(Asset $asset, int $limit = 10): array
    {
        /** @var list<Loan> $loans */
        $loans = $this->createQueryBuilder('l')
            ->join('l.lines', 'line')
            ->where('line.asset = :asset')
            ->setParameter('asset', $asset)
            ->orderBy('l.lentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        return $loans;
    }

    /**
     * Prêts dont l'emprunteur (avec compte actif) doit recevoir un rappel :
     * la veille de l'échéance, puis tous les trois jours tant que le retard dure.
     *
     * @return list<Loan>
     */
    public function findDueForReminder(\DateTimeImmutable $today): array
    {
        /** @var list<Loan> $loans */
        $loans = $this->createQueryBuilder('l')
            ->addSelect('u', 'line', 'a')
            ->join('l.borrowerAccount', 'u')
            ->join('l.lines', 'line')
            ->join('line.asset', 'a')
            ->where('l.closedAt IS NULL AND u.active = true')
            ->andWhere('(l.dueAt = :tomorrow AND l.lastReminderAt IS NULL) OR (l.dueAt < :today AND (l.lastReminderAt IS NULL OR l.lastReminderAt < :threeDaysAgo))')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $today->modify('+1 day'))
            ->setParameter('threeDaysAgo', $today->modify('-2 days'))
            ->orderBy('l.dueAt', 'ASC')
            ->getQuery()->getResult();

        return $loans;
    }

    private function restrictTo(QueryBuilder $qb, ?User $borrower): void
    {
        if (null !== $borrower) {
            $qb->andWhere('l.borrowerAccount = :borrower')->setParameter('borrower', $borrower);
        }
    }
}
