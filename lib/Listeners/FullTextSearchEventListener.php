<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Listeners;

use OCA\Deck\Db\Card;
use OCA\Deck\Event\AAclEvent;
use OCA\Deck\Event\ACardEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Provider\DeckProvider;
use OCA\Deck\Service\FullTextSearchService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\FullTextSearch\Exceptions\FullTextSearchAppNotAvailableException;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\FullTextSearch\Model\IIndex;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event|ACardEvent|AAclEvent> */
class FullTextSearchEventListener implements IEventListener {

	/** @var string|null */
	private $userId;
	/** @var IFullTextSearchManager|null */
	private $manager;
	/** @var FullTextSearchService|null */
	private $service;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(ContainerInterface $container, $userId) {
		$this->userId = $userId;
		$this->logger = $container->get(LoggerInterface::class);
		try {
			$this->manager = $container->get(IFullTextSearchManager::class);
			$this->service = $container->get(FullTextSearchService::class);
		} catch (\Exception $e) {
			// skipping in case FTS is not available
		}
	}

	public function handle(Event $event): void {
		if (!$event instanceof ACardEvent && !$event instanceof AAclEvent) {
			return;
		}

		try {
			if ($event instanceof CardCreatedEvent) {
				$this->manager->createIndex(
					DeckProvider::DECK_PROVIDER_ID, (string)$event->getCard()->getId(), $this->userId
				);
			}
			if ($event instanceof CardUpdatedEvent) {
				$this->manager->updateIndexStatus(
					DeckProvider::DECK_PROVIDER_ID, (string)$event->getCard()->getId(), IIndex::INDEX_CONTENT
				);
			}
			if ($event instanceof CardDeletedEvent) {
				$this->manager->updateIndexStatus(
					DeckProvider::DECK_PROVIDER_ID, (string)$event->getCard()->getId(), IIndex::INDEX_REMOVE
				);
			}

			if ($event instanceof AAclEvent) {
				$acl = $event->getAcl();
				$cards = array_map(
					static function (Card $card) {
						return (string)$card->getId();
					},
					$this->service->getCardsFromBoard($acl->getBoardId())
				);
				$this->manager->updateIndexesStatus(
					DeckProvider::DECK_PROVIDER_ID, $cards, IIndex::INDEX_META
				);
			}
		} catch (FullTextSearchAppNotAvailableException $e) {
			// Skip silently if no full text search app is available
		} catch (\Exception $e) {
			$this->logger->error('Error when handling deck full text search event', ['exception' => $e]);
		}
	}
}
