<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Activity;

use OCA\Deck\Db\Acl;
use OCA\Deck\Service\CardService;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class DeckProvider implements IProvider {

	/** @var string */
	private $userId;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ActivityManager */
	private $activityManager;
	/** @var IUserManager */
	private $userManager;
	/** @var ICommentsManager */
	private $commentsManager;
	/** @var IFactory */
	private $l10nFactory;
	/** @var IConfig */
	private $config;
	/** @var CardService */
	private $cardService;

	public function __construct(IURLGenerator $urlGenerator, ActivityManager $activityManager, IUserManager $userManager, ICommentsManager $commentsManager, IFactory $l10n, IConfig $config, $userId, CardService $cardService) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->commentsManager = $commentsManager;
		$this->userManager = $userManager;
		$this->l10nFactory = $l10n;
		$this->config = $config;
		$this->cardService = $cardService;
	}

	/**
	 * @param string $language The language which should be used for translating, e.g. "en"
	 * @param IEvent $event The current event which should be parsed
	 * @param IEvent|null $previousEvent A potential previous event which you can combine with the current one.
	 *                                   To do so, simply use setChildEvent($previousEvent) after setting the
	 *                                   combined subject on the current event.
	 * @return IEvent
	 * @throws \InvalidArgumentException Should be thrown if your provider does not know this event
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== 'deck') {
			throw new \InvalidArgumentException();
		}

		$event = $this->getIcon($event);

		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$ownActivity = ($event->getAuthor() === $this->userId);

		/**
		 * Map stored parameter objects to rich string types
		 */

		$author = $event->getAuthor();
		// get author if
		if (($author === '' || $author === ActivityManager::DECK_NOAUTHOR_COMMENT_SYSTEM_ENFORCED) && array_key_exists('author', $subjectParams)) {
			$author = $subjectParams['author'];
			unset($subjectParams['author']);
		}
		$user = $this->userManager->get($author);
		$params = [];
		if ($user !== null) {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => $author,
					'name' => $user->getDisplayName()
				],
			];
			$event->setAuthor($author);
		} else {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => 'deleted_users',
					'name' => 'deleted_users',
				]
			];
		}
		if ($event->getObjectType() === ActivityManager::DECK_OBJECT_BOARD) {
			if (!$this->activityManager->canSeeBoardActivity($event->getObjectId(), $event->getAffectedUser())) {
				throw new \InvalidArgumentException();
			}
			if (isset($subjectParams['board']) && $event->getObjectName() === '') {
				$event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['board']['title']);
			}
			$board = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
				'link' => $this->deckUrl('/board/' . $event->getObjectId()),
			];
			$params['board'] = $board;
			$event->setLink($this->deckUrl('/board/' . $event->getObjectId()));
		}

		if (isset($subjectParams['card']) && $event->getObjectType() === ActivityManager::DECK_OBJECT_CARD) {
			if (!$this->activityManager->canSeeCardActivity($event->getObjectId(), $event->getAffectedUser())) {
				throw new \InvalidArgumentException();
			}
			if ($event->getObjectName() === '') {
				$event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['card']['title']);
			}
			$card = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
			];

			if (array_key_exists('board', $subjectParams)) {
				$card['link'] = $this->cardService->getCardUrl($event->getObjectId());
				$event->setLink($card['link']);
			}
			$params['card'] = $card;
		}

		$params = $this->parseParamForBoard('board', $subjectParams, $params);
		$params = $this->parseParamForStack('stack', $subjectParams, $params);
		$params = $this->parseParamForStack('stackBefore', $subjectParams, $params);
		$params = $this->parseParamForAttachment('attachment', $subjectParams, $params);
		$params = $this->parseParamForLabel($subjectParams, $params);
		$params = $this->parseParamForAssignedUser($subjectParams, $params);
		$params = $this->parseParamForAcl($subjectParams, $params);
		if ($subjectIdentifier !== ActivityManager::SUBJECT_CARD_UPDATE_STACKID) {
			$params = $this->parseParamForChanges($subjectParams, $params, $event);
		}
		$params = $this->parseParamForComment($subjectParams, $params, $event);
		$params = $this->parseParamForDuedate($subjectParams, $params, $event);

		try {
			$subject = $this->activityManager->getActivityFormat($language, $subjectIdentifier, $subjectParams, $ownActivity);
			$this->setSubjects($event, $subject, $params);
		} catch (\Exception $e) {
		}
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters) {
		$placeholders = $replacements = $richParameters = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (is_array($parameter) && array_key_exists('name', $parameter)) {
				$replacements[] = $parameter['name'];
				$richParameters[$placeholder] = $parameter;
			} else {
				$replacements[] = '';
			}
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richParameters);
		$event->setSubject($subject, $parameters);
	}

	private function getIcon(IEvent $event) {
		$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'deck-dark.svg')));
		if (str_contains($event->getSubject(), '_update')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('files', 'change.svg')));
		}
		if (str_contains($event->getSubject(), '_create')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('files', 'add-color.svg')));
		}
		if (str_contains($event->getSubject(), '_delete')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('files', 'delete-color.svg')));
		}
		if (str_contains($event->getSubject(), 'archive')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'archive.svg')));
		}
		if (str_contains($event->getSubject(), '_restore')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/history.svg')));
		}
		if (str_contains($event->getSubject(), 'attachment_')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'places/files.svg')));
		}
		if (str_contains($event->getSubject(), 'comment_')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/comment.svg')));
		}
		if (str_contains($event->getSubject(), 'label_')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/tag.svg')));
		}
		return $event;
	}

	private function parseParamForBoard($paramName, $subjectParams, $params) {
		if (array_key_exists($paramName, $subjectParams)) {
			$params[$paramName] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams[$paramName]['id'],
				'name' => $subjectParams[$paramName]['title'],
				'link' => $this->deckUrl('/board/' . $subjectParams[$paramName]['id'] . '/'),
			];
		}
		return $params;
	}
	private function parseParamForStack($paramName, $subjectParams, $params) {
		if (array_key_exists($paramName, $subjectParams)) {
			$params[$paramName] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams[$paramName]['id'],
				'name' => $subjectParams[$paramName]['title'],
			];
		}
		return $params;
	}

	private function parseParamForAttachment($paramName, $subjectParams, $params) {
		if (array_key_exists($paramName, $subjectParams)) {
			$params[$paramName] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams[$paramName]['id'],
				'name' => $subjectParams[$paramName]['data'],
				'link' => $this->urlGenerator->linkToRoute('deck.attachment.display', ['cardId' => $subjectParams['card']['id'], 'attachmentId' => $subjectParams['attachment']['id']]),
			];
		}
		return $params;
	}

	private function parseParamForAssignedUser($subjectParams, $params) {
		if (array_key_exists('assigneduser', $subjectParams)) {
			$user = $this->userManager->get($subjectParams['assigneduser']);
			$params['assigneduser'] = [
				'type' => 'user',
				'id' => $subjectParams['assigneduser'],
				'name' => $user !== null ? $user->getDisplayName() : $subjectParams['assigneduser']
			];
		}
		return $params;
	}

	private function parseParamForLabel($subjectParams, $params) {
		if (array_key_exists('label', $subjectParams)) {
			$params['label'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['label']['id'],
				'name' => $subjectParams['label']['title']
			];
		}
		return $params;
	}

	private function parseParamForAcl($subjectParams, $params) {
		if (array_key_exists('acl', $subjectParams)) {
			if ($subjectParams['acl']['type'] === Acl::PERMISSION_TYPE_USER) {
				$user = $this->userManager->get($subjectParams['acl']['participant']);
				$params['acl'] = [
					'type' => 'user',
					'id' => $subjectParams['acl']['participant'],
					'name' => $user !== null ? $user->getDisplayName() : $subjectParams['acl']['participant']
				];
			} else {
				$params['acl'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['acl']['participant'],
					'name' => $subjectParams['acl']['participant']
				];
			}
		}
		return $params;
	}

	private function parseParamForComment($subjectParams, $params, IEvent $event) {
		if (array_key_exists('comment', $subjectParams)) {
			/** @var IComment $comment */
			try {
				$comment = $this->commentsManager->get($subjectParams['comment']);
				$event->setParsedMessage($comment->getMessage());
				$params['comment'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['comment'],
					'name' => $comment->getMessage()
				];
			} catch (NotFoundException $e) {
			}
		}
		return $params;
	}

	private function parseParamForDuedate($subjectParams, $params, IEvent $event) {
		if (array_key_exists('after', $subjectParams) && $event->getSubject() === ActivityManager::SUBJECT_CARD_UPDATE_DUEDATE) {
			$userLanguage = $this->config->getUserValue($event->getAuthor(), 'core', 'lang', $this->l10nFactory->findLanguage());
			$userLocale = $this->config->getUserValue($event->getAuthor(), 'core', 'locale', $this->l10nFactory->findLocale());
			$l10n = $this->l10nFactory->get('deck', $userLanguage, $userLocale);
			if (is_array($subjectParams['after'])) {
				// Unluckily there was a time when we stored jsonSerialized date objects in the database
				// Broken in 1.8.0 and fixed again in 1.8.1
				$date = new \DateTime($subjectParams['after']['date']);
				$date->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
			} else {
				$date = new \DateTime($subjectParams['after']);
				$date->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
			}
			$params['after'] = [
				'type' => 'highlight',
				'id' => 'dt:' . $subjectParams['after'],
				'name' => $l10n->l('datetime', $date),
			];
		}
		return $params;
	}

	/**
	 * Add diff to message if the subject parameter 'diff' is set, otherwise
	 * the changed values are added to before/after
	 *
	 * @param $subjectParams
	 * @param $params
	 * @return mixed
	 */
	private function parseParamForChanges($subjectParams, $params, $event) {
		if (array_key_exists('diff', $subjectParams) && $subjectParams['diff'] && !empty($subjectParams['after'])) {
			// Don't add diff as message since we are limited to 255 chars here
			$event->setParsedMessage($subjectParams['after']);
			return $params;
		}
		if (array_key_exists('before', $subjectParams)) {
			$params['before'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['before'],
				'name' => $subjectParams['before'] ?? ''
			];
		}
		if (array_key_exists('after', $subjectParams)) {
			$params['after'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['after'],
				'name' => $subjectParams['after'] ?? ''
			];
		}
		if (array_key_exists('card', $subjectParams) && $event->getSubject() === ActivityManager::SUBJECT_CARD_UPDATE_TITLE) {
			$params['card'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['after'],
				'name' => $subjectParams['after'] ?? ''
			];
		}
		return $params;
	}

	public function deckUrl($endpoint) {
		return $this->urlGenerator->linkToRouteAbsolute('deck.page.index') . trim($endpoint, '/');
	}
}
