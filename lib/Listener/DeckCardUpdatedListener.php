<?php

declare(strict_types=1);

namespace OCA\ProjectCreatorAIO\Listener;

use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\ProjectCreatorAIO\Service\DeckDoneSyncService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<CardUpdatedEvent|CardCreatedEvent>
 */
class DeckCardUpdatedListener implements IEventListener
{
	public function __construct(
		private readonly DeckDoneSyncService $doneSyncService,
	) {
	}

	public function handle(Event $event): void
	{
		if (!($event instanceof CardUpdatedEvent) && !($event instanceof CardCreatedEvent)) {
			return;
		}

		$this->doneSyncService->syncFromDeckCardEvent($event);
	}
}
