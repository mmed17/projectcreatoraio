<?php

namespace OCA\ProjectCreatorAIO\Service;

use DateTime;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Event\ACardEvent;
use OCA\Deck\Db\Card as DeckCard;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Throwable;

class DeckDoneSyncService
{
	private const APPROVED_DONE_STACK_ORDER = 4;

	public function __construct(
		private readonly IDBConnection $db,
		private readonly ProjectMapper $projectMapper,
		private readonly ChangeHelper $changeHelper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function syncFromDeckCardEvent(ACardEvent $event): int
	{
		$card = $event->getCard();
		if (!$card instanceof DeckCard) {
			return 0;
		}

		$cardId = (int) $card->getId();
		if ($cardId <= 0) {
			return 0;
		}

		$project = $this->projectMapper->findByCardId($cardId);
		if (!$project instanceof Project) {
			return 0;
		}

		return $this->syncCardForProject(
			(int) $project->getId(),
			(int) ($project->getType() ?? -1),
			$cardId,
			(string) $card->getTitle(),
			(int) $card->getStackId(),
			$card->getDone() instanceof \DateTime ? $card->getDone() : null,
		);
	}

	public function syncProject(Project $project): int
	{
		$projectId = (int) $project->getId();
		$projectType = (int) ($project->getType() ?? -1);

		$requiredTitles = ProjectTypeDeckDefaults::getRequiredNextPriorityTitles($projectType);
		if ($projectId <= 0 || $requiredTitles === []) {
			return 0;
		}

		$boardId = $this->parseIntOrZero($project->getBoardId());
		if ($boardId <= 0) {
			return 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('c.id', 'c.title', 'c.stack_id', 'c.done', 's.order')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', 'c.stack_id = s.id')
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('c.title', $qb->createNamedParameter($requiredTitles, IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $qb->executeQuery();

		$changed = 0;
		while ($row = $result->fetch()) {
			$cardId = (int) ($row['id'] ?? 0);
			$title = (string) ($row['title'] ?? '');
			$stackId = (int) ($row['stack_id'] ?? 0);
			$stackOrder = (int) ($row['order'] ?? -1);
			$done = $this->parseDateTimeOrNull($row['done'] ?? null);

			$changed += $this->syncCardForProject($projectId, $projectType, $cardId, $title, $stackId, $done, $stackOrder);
		}
		$result->closeCursor();

		return $changed;
	}

	private function syncCardForProject(
		int $projectId,
		int $projectType,
		int $cardId,
		string $title,
		int $stackId,
		?\DateTime $done,
		?int $stackOrderOverride = null,
	): int {
		if ($projectId <= 0 || $cardId <= 0 || $stackId <= 0) {
			return 0;
		}

		$requiredTitles = ProjectTypeDeckDefaults::getRequiredNextPriorityTitles($projectType);
		if ($requiredTitles === [] || !in_array($title, $requiredTitles, true)) {
			return 0;
		}

		try {
			$stackOrder = $stackOrderOverride ?? $this->getStackOrder($stackId);
			if ($stackOrder === null) {
				return 0;
			}

			$isApproved = $stackOrder === self::APPROVED_DONE_STACK_ORDER;
			$isManaged = $this->getManagedDoneFlag($projectId, $cardId);

			if ($isApproved && $done === null) {
				$this->setCardDone($cardId, new DateTime('now'));
				$this->setManagedDoneFlag($projectId, $cardId, true);
				$this->changeHelper->cardChanged($cardId, true);
				return 1;
			}

			if (!$isApproved && $isManaged) {
				$this->setCardDone($cardId, null);
				$this->setManagedDoneFlag($projectId, $cardId, false);
				$this->changeHelper->cardChanged($cardId, true);
				return 1;
			}

			return 0;
		} catch (Throwable $e) {
			$this->logger->warning('Deck done-sync failed for card', [
				'exception' => $e,
				'projectId' => $projectId,
				'cardId' => $cardId,
				'title' => $title,
				'stackId' => $stackId,
			]);
			return 0;
		}
	}

	private function getStackOrder(int $stackId): ?int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('order')
			->from('deck_stacks')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!is_array($row)) {
			return null;
		}
		return isset($row['order']) ? (int) $row['order'] : null;
	}

	private function setCardDone(int $cardId, ?DateTime $done): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update('deck_cards')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));

		if ($done === null) {
			$qb->set('done', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL));
		} else {
			$qb->set('done', $qb->createNamedParameter($done, IQueryBuilder::PARAM_DATETIME_MUTABLE));
		}

		$qb->executeStatement();
	}

	private function getManagedDoneFlag(int $projectId, int $cardId): bool
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('managed_done')
			->from('project_deck_done_sync')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return is_array($row) ? (bool) ($row['managed_done'] ?? 0) : false;
	}

	private function setManagedDoneFlag(int $projectId, int $cardId, bool $managed): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('project_deck_done_sync')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$now = new DateTime('now');

		if (is_array($row) && isset($row['id'])) {
			$update = $this->db->getQueryBuilder();
			$update->update('project_deck_done_sync')
				->set('managed_done', $update->createNamedParameter((int) $managed, IQueryBuilder::PARAM_INT))
				->set('updated_at', $update->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
				->where($update->expr()->eq('id', $update->createNamedParameter((int) $row['id'], IQueryBuilder::PARAM_INT)))
				->executeStatement();
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('project_deck_done_sync')
			->setValue('project_id', $insert->createNamedParameter($projectId, IQueryBuilder::PARAM_INT))
			->setValue('card_id', $insert->createNamedParameter($cardId, IQueryBuilder::PARAM_INT))
			->setValue('managed_done', $insert->createNamedParameter((int) $managed, IQueryBuilder::PARAM_INT))
			->setValue('created_at', $insert->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->setValue('updated_at', $insert->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->executeStatement();
	}

	private function parseDateTimeOrNull(mixed $value): ?DateTime
	{
		if ($value instanceof DateTime) {
			return $value;
		}
		if (is_string($value) && trim($value) !== '') {
			try {
				return new DateTime($value);
			} catch (Throwable $e) {
				return null;
			}
		}
		return null;
	}

	private function parseIntOrZero(?string $value): int
	{
		$value = (string) ($value ?? '');
		$value = trim($value);
		if ($value === '' || !ctype_digit($value)) {
			return 0;
		}
		return (int) $value;
	}
}
