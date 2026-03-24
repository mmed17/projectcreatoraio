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
	private const IMPORTANT_LABEL_TITLE = 'Kritieke Processtap';
	private const LEGACY_IMPORTANT_LABEL_TITLES = ['Belangrijk'];
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
		$label = $this->findLabelByTitle($board, self::IMPORTANT_LABEL_TITLE);
		if ($label !== null) {
			return (int) $label->getId();
		}

		foreach (self::LEGACY_IMPORTANT_LABEL_TITLES as $legacyTitle) {
			$legacyLabel = $this->findLabelByTitle($board, $legacyTitle);
			if ($legacyLabel === null) {
				continue;
			}

			try {
				$updatedLabel = $this->labelService->update(
					(int) $legacyLabel->getId(),
					self::IMPORTANT_LABEL_TITLE,
					self::IMPORTANT_LABEL_COLOR
				);
				return (int) $updatedLabel->getId();
			} catch (Throwable $e) {
				$this->logger->warning('Unable to rename legacy important label; using existing label title', [
					'boardId' => $boardId,
					'legacyTitle' => $legacyTitle,
					'exception' => $e,
				]);
				return (int) $legacyLabel->getId();
			}
		}

		try {
			/** @var Label $label */
			$label = $this->labelService->create(self::IMPORTANT_LABEL_TITLE, self::IMPORTANT_LABEL_COLOR, $boardId);
			return (int)$label->getId();
		} catch (Throwable $e) {
			try {
				$refreshedBoard = $this->boardService->find($boardId, true);
				$refreshedLabel = $this->findLabelByTitle($refreshedBoard, self::IMPORTANT_LABEL_TITLE);
				return $refreshedLabel !== null ? (int) $refreshedLabel->getId() : null;
			} catch (Throwable $e2) {
				$this->logger->warning('Unable to create/find important label; important cards will be unlabelled', [
					'boardId' => $boardId,
					'exception' => $e2,
				]);
				return null;
			}
		}
	}

	private function findLabelByTitle(Board $board, string $title): ?Label
	{
		$labels = $board->getLabels() ?? [];
		foreach ($labels as $label) {
			if ($label instanceof Label && $label->getTitle() === $title) {
				return $label;
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
					$signKeys = (array) ($policy['sign'] ?? $policy['approve'] ?? []);
					$verifyKeys = (array) ($policy['verify'] ?? $policy['approve'] ?? []);
					$this->cardPolicyService->setCardPolicyByRoleKeys(
						$boardId,
						(int) $card->getId(),
						(array) ($policy['move'] ?? []),
						$signKeys,
						$verifyKeys,
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
	 * @return array<string, array{move: string[], sign: string[], verify: string[]}>
	 */
	private function getCardPolicyByTitle(int $projectType): array
	{
		if ($projectType !== ProjectTypeDeckDefaults::TYPE_COMBI) {
			return [];
		}

		$policyByTitle = [
			// Process steps
			'Garantie overeenkomst' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'VO' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'DO' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Intake inplannen & hosten' => ['move' => ['cpl'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Intakeverslag' => ['move' => ['cpl'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Huisnummerbesluit' => ['move' => ['client_developer', 'cpl'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Hoogbouwoverleg inplannen' => ['move' => ['cpl'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'VO inpandige tekeningen' => ['move' => ['client_developer'], 'sign' => ['grid_operator'], 'verify' => ['grid_operator']],
			'DO inpandige tekeningen' => ['move' => ['client_developer'], 'sign' => ['grid_operator'], 'verify' => ['grid_operator']],
			'Verslag inpandig overleg' => ['move' => ['client_developer'], 'sign' => ['grid_operator'], 'verify' => ['grid_operator']],
			'Blokkenschema' => ['move' => ['client_developer'], 'sign' => ['grid_operator'], 'verify' => ['grid_operator']],
			'Aanvraag particuliere grond' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Bodemrapport' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Saneringsevaluatierapport' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Zakelijkrecht' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],

			// Next priority
			'Piekvermogensformulier' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Situatie tekening' => ['move' => ['client_developer'], 'sign' => ['grid_operator'], 'verify' => ['grid_operator']],
			'Intakeformulier' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'Quickscan' => ['move' => ['client_developer'], 'sign' => ['cpl'], 'verify' => ['cpl']],
			'AVP' => ['move' => ['grid_operator'], 'sign' => ['grid_operator'], 'verify' => ['grid_operator']],
		];

		// For Combi defaults, keep sign/verify aligned with a single approval role-set.
		foreach ($policyByTitle as &$policy) {
			$approval = (array) ($policy['approve'] ?? $policy['sign'] ?? $policy['verify'] ?? []);
			$policy['sign'] = $approval;
			$policy['verify'] = $approval;
			unset($policy['approve']);
		}
		unset($policy);

		return $policyByTitle;
	}
}
