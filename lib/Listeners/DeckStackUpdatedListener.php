<?php

declare(strict_types=1);

namespace OCA\Deck\Listeners;

use OC\Log;
use OCA\Deck\Event\StackUpdatedEvent;
use OCA\Deck\Service\StackService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class DeckStackUpdatedListener implements IEventListener
{
    public function __construct(
        private readonly Log $logger,
        private readonly StackService $stackService,
    ) {
    }

    public function handle(Event $event): void
    {
        if ( !$event instanceof StackUpdatedEvent ) {
            $this->logger->info('Event caught: CardStackUpdatedListener -- But not correct instance?');
            return;
        }

        $this->logger->info('Event caught: CardStackUpdatedListener', [
            'event' => ['stack_id' => $event->getStack()->getId(), 'object' => $event->getStack()],
            'previous' => ['stack_id' => $event->getBefore()->getId(), 'object' => $event->getBefore()],
        ]);
    }
}
