<?php

declare(strict_types=1);

namespace OCA\Deck\Listeners;

use OCA\Deck\Event\CardCreatedAttachmentEvent;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\DeckCommentActivityService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class DeckCardCreatedAttachmentListener implements IEventListener
{

	public function __construct(
		private readonly CardService $cardService,
		private readonly AttachmentService $attachmentService,
		private readonly DeckCommentActivityService $deckCommentActivityService,
	) {
	}

	public function handle(Event $event): void
	{
		// @TODO; Check event for attachment added
		if ( !$event instanceof CardCreatedAttachmentEvent ) {
			$this->logger->info('Event caught: DeckCardCreatedAttachmentListener -- But not correct instance?');
			return;
		}

		[$currentCard] = $this->cardService->enrichCards([
			$event->getCard(),
		]);

		/* @var \OCA\Deck\Db\Attachment $attachment */
		$attachment = $event->getAttachement();
		$attachment->getData();

		// Comment to card service -> $card & Attachment title
		$this->deckCommentActivityService->cardNewAttachment($currentCard);
	}
}
