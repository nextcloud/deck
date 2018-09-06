<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Activity;


use cogpowered\FineDiff\Diff;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;

class DeckProvider implements IProvider {

	/** @var string */
	private $userId;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ActivityManager */
	private $activityManager;

	public function __construct(IURLGenerator $urlGenerator, ActivityManager $activityManager, $userId) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
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
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'deck') {
			throw new \InvalidArgumentException();
		}

		$event->setIcon($this->urlGenerator->imagePath('deck', 'deck-dark.svg'));
		if (strpos($event->getSubject(), '_update') !== false) {
			$event->setIcon($this->urlGenerator->imagePath('files', 'change.svg'));
		}
		if (strpos($event->getSubject(), '_create') !== false) {
			$event->setIcon($this->urlGenerator->imagePath('files', 'add-color.svg'));
		}
		if (strpos($event->getSubject(), '_delete') !== false) {
			$event->setIcon($this->urlGenerator->imagePath('files', 'delete-color.svg'));
		}
		if (strpos($event->getSubject(), 'archive') !== false) {
			$event->setIcon($this->urlGenerator->imagePath('deck', 'archive.svg'));
		}
		if (strpos($event->getSubject(), '_restore') !== false) {
			$event->setIcon($this->urlGenerator->imagePath('core', 'actions/history.svg'));
		}
		if (strpos($event->getSubject(), 'attachment_') !== false) {
			$event->setIcon($this->urlGenerator->imagePath('core', 'places/files.svg'));
		}


		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();

		$ownActivity = ($event->getAuthor() === $this->userId);

		$board = null;
		if ($event->getObjectType() === ActivityManager::DECK_OBJECT_BOARD) {
			$board = [
				'type' => 'highlight',
				'id' => $event->getObjectId(),
				'name' => $event->getObjectName(),
				'link' => $this->deckUrl('/board/' . $event->getObjectId()),
			];
		}

		$card = null;
		if ($event->getObjectType() === ActivityManager::DECK_OBJECT_CARD) {
			$card = [
				'type' => 'highlight',
				'id' => $event->getObjectId(),
				'name' => $event->getObjectName(),
			];

			if ($subjectParams['board']) {
				$archivedParam = $subjectParams['card']['archived'] ? 'archived' : '';
				$card['link'] = $this->deckUrl('/board/' . $subjectParams['board']['id'] . '/' . $archivedParam . '/card/' . $event->getObjectId());
			}
		}

		$userManager = \OC::$server->getUserManager();
		$author = $event->getAuthor();
		$user = $userManager->get($author);
		$params = [
			'board' => $board,
			'card' => $card,
			'user' => [
				'type' => 'user',
				'id' => $author,
				'name' => $user !== null ? $user->getDisplayName() : $author
			]
		];

		if (array_key_exists('stack', $subjectParams)) {
			$params['stack'] = [
				'type' => 'highlight',
				'id' => $subjectParams['stack']['id'],
				'name' => $subjectParams['stack']['title'],
				'link' => $this->deckUrl('/board/' . $subjectParams['stack']['boardId'] . '/'),
			];
		}

		if (array_key_exists('board', $subjectParams)) {
			$params['board'] = [
				'type' => 'highlight',
				'id' => $subjectParams['board']['id'],
				'name' => $subjectParams['board']['title'],
				'link' => $this->deckUrl('/board/' . $subjectParams['board']['id'] . '/'),
			];
		}

		if (array_key_exists('label', $subjectParams)) {
			$params['label'] = [
				'type' => 'highlight',
				'id' => $subjectParams['label']['id'],
				'name' => $subjectParams['label']['title']
			];
		}

		if (array_key_exists('attachment', $subjectParams)) {
			$params['attachment'] = [
				'type' => 'highlight',
				'id' => $subjectParams['attachment']['id'],
				'name' => $subjectParams['attachment']['data'],
				'link' => $this->urlGenerator->linkToRoute('deck.attachment.display', ['cardId' => $subjectParams['card']['id'], 'attachmentId' => $subjectParams['attachment']['id']]),
			];
		}

		if (array_key_exists('assigneduser', $subjectParams)) {
			$user = $userManager->get($subjectParams['assigneduser']);
			$params['assigneduser'] = [
				'type' => 'user',
				'id' => $subjectParams['assigneduser'],
				'name' => $user !== null ? $user->getDisplayName() : $subjectParams['assigneduser']
			];
		}

		if (array_key_exists('before', $subjectParams)) {
			$params['before'] = [
				'type' => 'highlight',
				'id' => $subjectParams['before'],
				'name' => $subjectParams['before']
			];
		}
		if (array_key_exists('after', $subjectParams)) {
			$params['after'] = [
				'type' => 'highlight',
				'id' => $subjectParams['after'],
				'name' => $subjectParams['after']
			];
		}

		try {
			$subject = $this->activityManager->getActivityFormat($subjectIdentifier, $subjectParams, $ownActivity);
		} catch (\Exception $e) {
			return $event;
		}

		$event->setParsedSubject($subject);
		$event->setRichSubject(
			$subject,
			$params
		);
		if ($event->getMessage() !== '') {
			$diff = new Diff();
			$event->setParsedMessage('<pre class="visualdiff">' . $diff->render($subjectParams['before'], $subjectParams['after']) . '</pre>');
		}

		return $event;
	}

	public function deckUrl($endpoint) {
		return $this->urlGenerator->linkToRoute('deck.page.index') . '#!' . $endpoint;
	}
}
