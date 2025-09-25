<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCP\Notification\UnknownNotificationException;

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
		BoardMapper $boardMapper,
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
		if ($notification->getApp() !== 'deck' || $notification->getObjectType() === 'activity_notification') {
			throw new UnknownNotificationException();
		}
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('deck', 'deck-dark.svg')));
		$params = $notification->getSubjectParameters();

		switch ($notification->getSubject()) {
			case 'card-assigned':
				$cardId = (int)$notification->getObjectId();
				$stack = $this->stackMapper->findStackFromCardId($cardId);
				$boardId = $stack ? (int)$stack->getBoardId() : null;
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
							'id' => (string)$cardId,
							'name' => $params[0],
							'boardname' => (string)$params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'deck-board' => [
							'type' => 'deck-board',
							'id' => (string)$boardId,
							'name' => (string)$params[1],
							'link' => $this->getBoardUrl($boardId),
						],
						'user' => [
							'type' => 'user',
							'id' => (string)$params[2],
							'name' => $dn,
						]
					]
				);
				$notification->setLink($this->getCardUrl($boardId, $cardId));
				break;
			case 'card-overdue':
				$cardId = (int)$notification->getObjectId();
				$stack = $this->stackMapper->findStackFromCardId($cardId);
				$boardId = $stack ? (int)$stack->getBoardId() : null;
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
							'id' => (string)$cardId,
							'name' => (string)$params[0],
							'boardname' => (string)$params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'deck-board' => [
							'type' => 'deck-board',
							'id' => (string)$boardId,
							'name' => (string)$params[1],
							'link' => $this->getBoardUrl($boardId),
						],
					]
				);
				$notification->setLink($this->getCardUrl($boardId, $cardId));
				break;
			case 'card-comment-mentioned':
				$cardId = (int)$notification->getObjectId();
				$stack = $this->stackMapper->findStackFromCardId($cardId);
				$boardId = $stack ? (int)$stack->getBoardId() : null;
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
							'id' => (string)$cardId,
							'name' => (string)$params[0],
							'boardname' => (string)$params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'user' => [
							'type' => 'user',
							'id' => (string)$params[2],
							'name' => $dn,
						]
					]
				);
				if ($notification->getMessage() === '{message}') {
					$notification->setParsedMessage($notification->getMessageParameters()['message']);
				}
				$notification->setLink($this->getCardUrl($boardId, $cardId));
				break;
			case 'board-shared':
				$boardId = (int)$notification->getObjectId();
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
							'id' => (string)$boardId,
							'name' => (string)$params[0],
							'link' => $this->getBoardUrl($boardId),
						],
						'user' => [
							'type' => 'user',
							'id' => $params[1] ?? '',
							'name' => $dn ?? '',
						]
					]
				);
				$notification->setLink($this->getBoardUrl($boardId));
				break;
		}
		return $notification;
	}

	private function getBoardUrl(int $boardId): string {
		return $this->url->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $boardId]);
	}

	private function getCardUrl(int $boardId, int $cardId): string {
		return $this->url->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
	}
}
