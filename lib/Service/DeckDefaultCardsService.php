<?php

namespace OCA\ProjectCreatorAIO\Service;

use OCA\Deck\Db\Board;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\CardPolicyService;
use OCA\Deck\Service\LabelService;
use OCA\Deck\Service\StackService;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Throwable;

class DeckDefaultCardsService
{
	private const IMPORTANT_LABEL_TITLE = 'Belangrijk';
	private const IMPORTANT_LABEL_COLOR = 'FF0000';

	public function __construct(
		private readonly CardService $cardService,
		private readonly CardPolicyService $cardPolicyService,
		private readonly LabelService $labelService,
		private readonly StackService $stackService,
		private readonly BoardService $boardService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function seedForProjectType(int $projectType, Board $board, IUser $owner): void
	{
		$nextPriorityCards = ProjectTypeDeckDefaults::getNextPriorityCards($projectType);
		$processStepCards = ProjectTypeDeckDefaults::getProcessStepCards($projectType);

		if ($nextPriorityCards === [] && $processStepCards === []) {
			return;
		}

		try {
			$boardId = (int)$board->getId();
			if ($boardId <= 0) {
				return;
			}

			// Enable new card-policy permissions for project boards.
			$this->cardPolicyService->enableCardPolicyMode($boardId);

			$board = $this->boardService->find($boardId, true);

			$stacks = $this->getBoardStacks($board, $owner);
			$processStepsStack = $this->findStackByOrder($stacks, 0);
			$nextPriorityStack = $this->findStackByOrder($stacks, 1);

			if ($processStepsStack === null || $nextPriorityStack === null) {
				$this->logger->warning('Deck default card seeding skipped: missing default stacks', [
					'boardId' => $boardId,
					'hasProcessSteps' => $processStepsStack !== null,
					'hasNextPriority' => $nextPriorityStack !== null,
				]);
				return;
			}

			$importantLabelId = $this->ensureImportantLabelId($boardId, $board, $owner);

			$policyByTitle = $this->getCardPolicyByTitle($projectType);

			$this->seedCardsIntoStack(
				$boardId,
				$nextPriorityStack,
				$nextPriorityCards,
				$owner->getUID(),
				$importantLabelId,
				$policyByTitle,
			);

			$this->seedCardsIntoStack(
				$boardId,
				$processStepsStack,
				$processStepCards,
				$owner->getUID(),
				$importantLabelId,
				$policyByTitle,
			);
		} catch (Throwable $e) {
			$this->logger->error('Deck default card seeding failed', [
				'exception' => $e,
				'boardId' => $board->getId(),
				'projectType' => $projectType,
			]);
		}
	}

	/**
	 * @return Stack[]
	 */
	private function getBoardStacks(Board $board, IUser $owner): array
	{
		$stacks = $board->getStacks() ?? [];
		if (!empty($stacks)) {
			return $stacks;
		}

		return $this->stackService->findAll($owner->getUID(), (string)$board->getId());
	}

	/**
	 * @param Stack[] $stacks
	 */
	private function findStackByOrder(array $stacks, int $order): ?Stack
	{
		foreach ($stacks as $stack) {
			if ((int)$stack->getOrder() === $order) {
				return $stack;
			}
		}
		return null;
	}

	private function ensureImportantLabelId(int $boardId, Board $board, IUser $owner): ?int
	{
		$labelId = $this->findLabelIdByTitle($board, self::IMPORTANT_LABEL_TITLE);
		if ($labelId !== null) {
			return $labelId;
		}

		try {
			/** @var Label $label */
			$label = $this->labelService->create(self::IMPORTANT_LABEL_TITLE, self::IMPORTANT_LABEL_COLOR, $boardId);
			return (int)$label->getId();
		} catch (Throwable $e) {
			try {
				$refreshedBoard = $this->boardService->find($boardId, true);
				return $this->findLabelIdByTitle($refreshedBoard, self::IMPORTANT_LABEL_TITLE);
			} catch (Throwable $e2) {
				$this->logger->warning('Unable to create/find important label; important cards will be unlabelled', [
					'boardId' => $boardId,
					'exception' => $e2,
				]);
				return null;
			}
		}
	}

	private function findLabelIdByTitle(Board $board, string $title): ?int
	{
		$labels = $board->getLabels() ?? [];
		foreach ($labels as $label) {
			if ($label instanceof Label && $label->getTitle() === $title) {
				return (int)$label->getId();
			}
		}
		return null;
	}

	/**
	 * @param array<int, array{title: string, important: bool}> $cards
	 */
	private function seedCardsIntoStack(int $boardId, Stack $stack, array $cards, string $ownerUid, ?int $importantLabelId, array $policyByTitle): void
	{
		foreach ($cards as $index => $cardTemplate) {
			try {
				$card = $this->cardService->create(
					$cardTemplate['title'],
					(int)$stack->getId(),
					'plain',
					(int)$index,
					$ownerUid,
					''
				);

				$title = (string) ($cardTemplate['title'] ?? '');
				if ($title !== '' && isset($policyByTitle[$title])) {
					$policy = $policyByTitle[$title];
					$this->cardPolicyService->setCardPolicyByRoleKeys(
						$boardId,
						(int) $card->getId(),
						(array) ($policy['move'] ?? []),
						(array) ($policy['approve'] ?? []),
					);
				}

				if ($importantLabelId !== null && ($cardTemplate['important'] ?? false)) {
					$this->cardService->assignLabel((int)$card->getId(), $importantLabelId);
				}
			} catch (Throwable $e) {
				$this->logger->warning('Deck default card seeding: unable to create/label card', [
					'exception' => $e,
					'stackId' => $stack->getId(),
					'stackOrder' => $stack->getOrder(),
					'title' => $cardTemplate['title'] ?? null,
				]);
				continue;
			}
		}
	}

	/**
	 * @return array<string, array{move: string[], approve: string[]}>
	 */
	private function getCardPolicyByTitle(int $projectType): array
	{
		if ($projectType !== ProjectTypeDeckDefaults::TYPE_COMBI) {
			return [];
		}

		return [
			// Process steps
			'Garantie overeenkomst' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'VO' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'DO' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'Intake inplannen & hosten' => ['move' => ['cpl'], 'approve' => ['cpl']],
			'Intakeverslag' => ['move' => ['cpl'], 'approve' => ['cpl']],
			'Huisnummerbesluit' => ['move' => ['client_developer', 'cpl'], 'approve' => ['cpl']],
			'Hoogbouwoverleg inplannen' => ['move' => ['cpl'], 'approve' => ['cpl']],
			'VO inpandige tekeningen' => ['move' => ['client_developer'], 'approve' => ['grid_operator']],
			'DO inpandige tekeningen' => ['move' => ['client_developer'], 'approve' => ['grid_operator']],
			'Verslag inpandig overleg' => ['move' => ['client_developer'], 'approve' => ['grid_operator']],
			'Blokkenschema' => ['move' => ['client_developer'], 'approve' => ['grid_operator']],
			'Aanvraag particuliere grond' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'Bodemrapport' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'Saneringsevaluatierapport' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'Zakelijkrecht' => ['move' => ['client_developer'], 'approve' => ['cpl']],

			// Next priority
			'Piekvermogensformulier' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'Situatie tekening' => ['move' => ['client_developer'], 'approve' => ['grid_operator']],
			'Intakeformulier' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'Quickscan' => ['move' => ['client_developer'], 'approve' => ['cpl']],
			'AVP' => ['move' => ['grid_operator'], 'approve' => ['grid_operator']],
		];
	}
}
