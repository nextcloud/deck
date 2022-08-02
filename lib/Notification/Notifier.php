<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Notification;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	/** @var IFactory */
	protected $l10nFactory;
	/** @var IURLGenerator */
	protected $url;
	/** @var IUserManager */
	protected $userManager;
	/** @var CardMapper */
	protected $cardMapper;
	/** @var StackMapper */
	protected $stackMapper;
	/** @var BoardMapper */
	protected $boardMapper;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $url,
		IUserManager $userManager,
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		BoardMapper $boardMapper
	) {
		$this->l10nFactory = $l10nFactory;
		$this->url = $url;
		$this->userManager = $userManager;
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'deck';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get('deck')->t('Deck');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get('deck', $languageCode);
		if ($notification->getApp() !== 'deck') {
			throw new \InvalidArgumentException();
		}
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('deck', 'deck-dark.svg')));
		$params = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case 'card-assigned':
				$cardId = $notification->getObjectId();
				$stack = $this->stackMapper->findStackFromCardId($cardId);
				$boardId = $stack ? $stack->getBoardId() : null;
				if (!$boardId) {
					throw new AlreadyProcessedException();
				}

				$initiator = $this->userManager->get($params[2]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[2];
				}
				$notification->setParsedSubject(
					$l->t('The card "%s" on "%s" has been assigned to you by %s.', [$params[0], $params[1], $dn])
				);
				$notification->setRichSubject(
					$l->t('{user} has assigned the card {deck-card} on {deck-board} to you.'),
					[
						'deck-card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[0],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $cardId . '',
						],
						'deck-board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[1],
							'link' => $this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId,
						],
						'user' => [
							'type' => 'user',
							'id' => $params[2],
							'name' => $dn,
						]
					]
				);
				$notification->setLink($this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $cardId . '');
				break;
			case 'card-overdue':
				$cardId = $notification->getObjectId();
				$stack = $this->stackMapper->findStackFromCardId($cardId);
				$boardId = $stack ? $stack->getBoardId() : null;
				if (!$boardId) {
					throw new AlreadyProcessedException();
				}

				$notification->setParsedSubject(
					$l->t('The card "%s" on "%s" has reached its due date.', $params)
				);
				$notification->setRichSubject(
					$l->t('The card {deck-card} on {deck-board} has reached its due date.'),
					[
						'deck-card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[0],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $cardId . '',
						],
						'deck-board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[1],
							'link' => $this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId,
						],
					]
				);
				$notification->setLink($this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $cardId . '');
				break;
			case 'card-comment-mentioned':
				$cardId = $notification->getObjectId();
				$stack = $this->stackMapper->findStackFromCardId($cardId);
				$boardId = $stack ? $stack->getBoardId() : null;
				if (!$boardId) {
					throw new AlreadyProcessedException();
				}

				$initiator = $this->userManager->get($params[2]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[2];
				}
				$notification->setParsedSubject(
					$l->t('%s has mentioned you in a comment on "%s".', [$dn, $params[0]])
				);
				$notification->setRichSubject(
					$l->t('{user} has mentioned you in a comment on {deck-card}.'),
					[
						'deck-card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[0],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $cardId . '',
						],
						'user' => [
							'type' => 'user',
							'id' => $params[2],
							'name' => $dn,
						]
					]
				);
				if ($notification->getMessage() === '{message}') {
					$notification->setParsedMessage($notification->getMessageParameters()['message']);
				}
				$notification->setLink($this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $cardId . '');
				break;
			case 'board-shared':
				$boardId = $notification->getObjectId();
				if (!$boardId) {
					throw new AlreadyProcessedException();
				}
				$initiator = $this->userManager->get($params[1]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[1];
				}
				$notification->setParsedSubject(
					$l->t('The board "%s" has been shared with you by %s.', [$params[0], $dn])
				);
				$notification->setRichSubject(
					$l->t('{user} has shared {deck-board} with you.'),
					[
						'deck-board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[0],
							'link' => $this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId,
						],
						'user' => [
							'type' => 'user',
							'id' => $params[1],
							'name' => $dn,
						]
					]
				);
				$notification->setLink($this->url->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/');
				break;
		}
		return $notification;
	}
}
