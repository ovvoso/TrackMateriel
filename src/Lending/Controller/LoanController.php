<?php

declare(strict_types=1);

namespace App\Lending\Controller;

use App\Identity\Entity\User;
use App\Inventory\Repository\AssetRepository;
use App\Lending\Entity\Loan;
use App\Lending\Enum\LoanState;
use App\Lending\Form\ExtendLoanType;
use App\Lending\Form\LoanInput;
use App\Lending\Form\LoanType;
use App\Lending\Form\ReturnLineInput;
use App\Lending\Form\ReturnLineType;
use App\Lending\Repository\LoanRepository;
use App\Lending\Repository\LoanSearch;
use App\Lending\Security\LoanVoter;
use App\Lending\Service\LendingException;
use App\Lending\Service\LoanManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/prets')]
#[IsGranted('ROLE_USAGER')]
final class LoanController extends AbstractController
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly LoanManager $manager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Le gestionnaire voit tous les prêts ; les autres profils, uniquement leurs emprunts.
     */
    #[Route('', name: 'app_loan_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $restrictTo = $this->isGranted(LoanVoter::MANAGE) ? null : $user;
        $search = LoanSearch::fromRequest($request, $restrictTo);
        $today = $this->today();

        return $this->render('lending/index.html.twig', [
            'search' => $search,
            'page' => $this->loans->search($search, $today),
            'counts' => $this->loans->countOpen($today, $restrictTo),
            'states' => LoanState::cases(),
            'today' => $today,
            'own_only' => null !== $restrictTo,
        ]);
    }

    #[Route('/nouveau', name: 'app_loan_new', methods: ['GET', 'POST'])]
    #[IsGranted(LoanVoter::MANAGE)]
    public function new(Request $request, AssetRepository $assets): Response
    {
        $input = new LoanInput();
        $input->dueAt = $this->today()->modify('+7 days');

        // Depuis la fiche d'un équipement : il est déjà coché
        $preselected = $assets->findOneByCode($request->query->getString('materiel'));
        if (null !== $preselected) {
            $input->assets = [$preselected];
        }

        $form = $this->createForm(LoanType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $loan = $this->manager->lend($input);
                $this->addFlash('success', \sprintf('Prêt %s enregistré : %d équipement%s à rendre le %s.', $loan->getReference(), \count($input->assets), \count($input->assets) > 1 ? 's' : '', $loan->getDueAt()->format('d/m/Y')));

                return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()], Response::HTTP_SEE_OTHER);
            } catch (LendingException $exception) {
                $form->get('assets')->addError(new FormError($exception->getMessage()));
            } catch (UniqueConstraintViolationException) {
                $form->get('assets')->addError(new FormError('Un des équipements vient d’être prêté par quelqu’un d’autre. Rechargez la page.'));
            }
        }

        return $this->render('lending/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_loan_show', methods: ['GET', 'POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted(LoanVoter::VIEW, subject: 'loan')]
    public function show(Request $request, Loan $loan): Response
    {
        $returnForm = null;
        $extendForm = null;

        if ($this->isGranted(LoanVoter::HANDLE, $loan)) {
            $returnForm = $this->returnForm($loan);
            $returnForm->handleRequest($request);
            if ($returnForm->isSubmitted() && $returnForm->isValid()) {
                return $this->handleReturn($loan, $returnForm);
            }

            $extendForm = $this->createForm(ExtendLoanType::class, null, [
                'current_due' => $loan->getDueAt(),
                'action' => $this->generateUrl('app_loan_extend', ['id' => $loan->getId()]),
            ]);
        }

        return $this->render('lending/show.html.twig', [
            'loan' => $loan,
            'state' => $loan->getState($this->today()),
            'days_late' => $loan->daysLate($this->today()),
            'return_form' => $returnForm,
            'extend_form' => $extendForm,
        ]);
    }

    #[Route('/{id}/prolonger', name: 'app_loan_extend', methods: ['POST'], requirements: ['id' => Requirement::UUID])]
    #[IsGranted(LoanVoter::HANDLE, subject: 'loan')]
    public function extend(Request $request, Loan $loan): Response
    {
        $form = $this->createForm(ExtendLoanType::class, null, ['current_due' => $loan->getDueAt()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dueAt = $form->get('dueAt')->getData();
            \assert($dueAt instanceof \DateTimeImmutable);
            $this->manager->extend($loan, $dueAt);
            $this->addFlash('success', \sprintf('Prêt prolongé jusqu’au %s.', $dueAt->format('d/m/Y')));
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return FormInterface<array{lines: list<ReturnLineInput>}>
     */
    private function returnForm(Loan $loan): FormInterface
    {
        $lines = array_map(static fn ($line) => new ReturnLineInput($line), $loan->getOutstandingLines());

        /** @var FormInterface<array{lines: list<ReturnLineInput>}> $form */
        $form = $this->createFormBuilder(['lines' => $lines], ['translation_domain' => false])
            ->add('lines', CollectionType::class, ['entry_type' => ReturnLineType::class, 'label' => false])
            ->getForm();

        return $form;
    }

    /**
     * @param FormInterface<array{lines: list<ReturnLineInput>}> $form
     */
    private function handleReturn(Loan $loan, FormInterface $form): Response
    {
        /** @var array{lines: list<ReturnLineInput>} $data */
        $data = $form->getData();
        $selected = array_filter($data['lines'], static fn (ReturnLineInput $line) => $line->returned);

        if ([] === $selected) {
            $this->addFlash('danger', 'Cochez le matériel rendu.');

            return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()], Response::HTTP_SEE_OTHER);
        }

        $damaged = $this->manager->receive($loan, $data['lines']);

        $this->addFlash('success', $loan->isClosed()
            ? \sprintf('Tout le matériel est rendu : le prêt %s est clos.', $loan->getReference())
            : \sprintf('%d retour%s enregistré%s. Il reste %d équipement%s à rendre.', \count($selected), \count($selected) > 1 ? 's' : '', \count($selected) > 1 ? 's' : '', \count($loan->getOutstandingLines()), \count($loan->getOutstandingLines()) > 1 ? 's' : ''));

        foreach ($damaged as $asset) {
            $this->addFlash('info', \sprintf('%s (%s) est revenu endommagé : pensez à l’envoyer en maintenance depuis sa fiche.', $asset->getName(), $asset->getCode()));
        }

        return $this->redirectToRoute('app_loan_show', ['id' => $loan->getId()], Response::HTTP_SEE_OTHER);
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }
}
