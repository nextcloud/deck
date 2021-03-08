<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Flow;

use OCA\Deck\Event\CardCreatedEvent;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\EntityContext\IDisplayName;
use OCP\WorkflowEngine\EntityContext\IDisplayText;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IRuleMatcher;

class CardEntity implements IEntity, IDisplayText, IDisplayName, IUrl  {

	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	
	private $card;

	public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
		$this->l10n = $l;
		$this->urlGenerator = $urlGenerator;
	}
	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Deck card');
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('deck', 'deck-dark.svg');
	}

	/**
	 * @inheritDoc
	 */
	public function getEvents(): array {
		return [new CardEntityCreatedEvent($this->l10n)];
	}

	/**
	 * @inheritDoc
	 */
	public function prepareRuleMatcher(IRuleMatcher $ruleMatcher, string $eventName, Event $event): void {
		if(!$event instanceof CardCreatedEvent) {
			return;
		}
		/** @var CardCreatedEvent $event */
		$ruleMatcher->setEntitySubject($this, $event->getCard());
		$this->card = $event->getCard();
	}

	/**
	 * @inheritDoc
	 */
	public function isLegitimatedForUserId(string $userId): bool {
		return true;
	}

	public function getDisplayName(): string {
		return $this->card->getTitle();
	}

	public function getDisplayText(int $verbosity = 0): string {
		return $this->card->getTitle() . PHP_EOL . $this->card->getDescription();
	}

	public function getUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('deck.page.index') . '#' . '/board/1/card/' . $this->card->getId();
	}
}
