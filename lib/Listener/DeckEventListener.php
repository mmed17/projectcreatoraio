<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Listener;

use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\AclUpdatedEvent;
use OCA\Deck\Event\BoardCreatedEvent;
use OCA\Deck\Event\BoardDeletedEvent;
use OCA\Deck\Event\BoardUpdatedEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Service\ProjectActivityService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;

/** @template-implements IEventListener<Event> */
class DeckEventListener implements IEventListener {
	public function __construct(
		private readonly ProjectMapper $projectMapper,
		private readonly StackMapper $stackMapper,
		private readonly ProjectActivityService $projectActivityService,
		private readonly IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		match (true) {
			$event instanceof BoardCreatedEvent => $this->handleBoardEvent($event, ProjectActivityService::EVENT_DECK_BOARD_CREATED),
			$event instanceof BoardUpdatedEvent => $this->handleBoardEvent($event, ProjectActivityService::EVENT_DECK_BOARD_UPDATED),
			$event instanceof BoardDeletedEvent => $this->handleBoardEvent($event, ProjectActivityService::EVENT_DECK_BOARD_DELETED),
			$event instanceof CardCreatedEvent => $this->handleCardCreated($event),
			$event instanceof CardUpdatedEvent => $this->handleCardUpdated($event),
			$event instanceof CardDeletedEvent => $this->handleCardDeleted($event),
			$event instanceof AclCreatedEvent => $this->handleAclCreated($event),
			$event instanceof AclUpdatedEvent => $this->handleAclUpdated($event),
			$event instanceof AclDeletedEvent => $this->handleAclDeleted($event),
			default => null,
		};
	}

	private function handleBoardEvent(Event $event, string $eventType): void {
		$boardId = $event->getBoardId();
		if ($boardId <= 0) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, $eventType, ProjectActivityService::SOURCE_DECK, $actor);
	}

	private function handleCardCreated(CardCreatedEvent $event): void {
		$card = $event->getCard();
		$boardId = $this->getBoardIdFromCard($card);
		if ($boardId === null) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_DECK_CARD_CREATED, ProjectActivityService::SOURCE_DECK, $actor, [
			'cardTitle' => $card->getTitle(),
			'cardId' => (int) ($card->getId() ?? 0),
		]);
	}

	private function handleCardUpdated(CardUpdatedEvent $event): void {
		$card = $event->getCard();
		$boardId = $this->getBoardIdFromCard($card);
		if ($boardId === null) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$before = $event->getCardBefore();
		$changes = [];
		if ($before !== null && $before->getTitle() !== $card->getTitle()) {
			$changes[] = 'title';
		}

		$this->projectActivityService->record($project, ProjectActivityService::EVENT_DECK_CARD_UPDATED, ProjectActivityService::SOURCE_DECK, $actor, [
			'cardTitle' => $card->getTitle(),
			'cardId' => (int) ($card->getId() ?? 0),
			'changes' => $changes,
		]);
	}

	private function handleCardDeleted(CardDeletedEvent $event): void {
		$card = $event->getCard();
		$boardId = $this->getBoardIdFromCard($card);
		if ($boardId === null) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_DECK_CARD_DELETED, ProjectActivityService::SOURCE_DECK, $actor, [
			'cardTitle' => $card->getTitle(),
			'cardId' => (int) ($card->getId() ?? 0),
		]);
	}

	private function handleAclCreated(AclCreatedEvent $event): void {
		$boardId = $event->getBoardId();
		if ($boardId <= 0) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$acl = $event->getAcl();
		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_DECK_ACL_ADDED, ProjectActivityService::SOURCE_DECK, $actor, [
			'participant' => $acl->getParticipant(),
			'type' => $acl->getType(),
		]);
	}

	private function handleAclUpdated(AclUpdatedEvent $event): void {
		$boardId = $event->getBoardId();
		if ($boardId <= 0) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$acl = $event->getAcl();
		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_DECK_ACL_UPDATED, ProjectActivityService::SOURCE_DECK, $actor, [
			'participant' => $acl->getParticipant(),
			'type' => $acl->getType(),
		]);
	}

	private function handleAclDeleted(AclDeletedEvent $event): void {
		$boardId = $event->getBoardId();
		if ($boardId <= 0) {
			return;
		}

		$project = $this->projectMapper->findByBoardId($boardId);
		if ($project === null) {
			return;
		}

		$acl = $event->getAcl();
		$actor = $this->userSession->getUser();
		$this->projectActivityService->record($project, ProjectActivityService::EVENT_DECK_ACL_REMOVED, ProjectActivityService::SOURCE_DECK, $actor, [
			'participant' => $acl->getParticipant(),
			'type' => $acl->getType(),
		]);
	}

	private function getBoardIdFromCard($card): ?int {
		$stackId = $card->getStackId();
		if ($stackId <= 0) {
			return null;
		}

		try {
			$stack = $this->stackMapper->find($stackId);
			if ($stack === null) {
				return null;
			}
			return $stack->getBoardId();
		} catch (\Throwable) {
			return null;
		}
	}
}
