<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;

class DefaultBoardService {
	private $boardMapper;
	private $boardService;
	private $stackService;
	private $cardService;
	private $config;
	private $l10n;
	private LabelService $labelService;
	private AttachmentService $attachmentService;

	public function __construct(
		IL10N $l10n,
		BoardMapper $boardMapper,
		BoardService $boardService,
		StackService $stackService,
		CardService $cardService,
		IConfig $config,
		LabelService $labelService,
		AttachmentService $attachmentService,
	) {
		$this->boardService = $boardService;
		$this->stackService = $stackService;
		$this->cardService = $cardService;
		$this->config = $config;
		$this->boardMapper = $boardMapper;
		$this->l10n = $l10n;
		$this->labelService = $labelService;
		$this->attachmentService = $attachmentService;
	}

	/**
	 * Return true if this is the first time a user is acessing their instance with deck enabled
	 *
	 * @param $userId
	 * @return bool
	 */
	public function checkFirstRun($userId): bool {
		$firstRun = $this->config->getUserValue($userId, Application::APP_ID, 'firstRun', 'yes');

		if ($firstRun === 'yes') {
			try {
				$this->config->setUserValue($userId, Application::APP_ID, 'firstRun', 'no');
			} catch (PreConditionNotMetException $e) {
				return false;
			}
			return true;
		}

		return false;
	}

	private function getDefaultBoardData(): array {
		$defaultBoardDataJson = file_get_contents(__DIR__ . '/fixtures/default-board.json');
		return json_decode($defaultBoardDataJson, true);
	}

	/**
	 * @param $userId
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCA\Deck\StatusException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function createDefaultBoard(string $title, string $userId, string $color) {
		$boardData = $this->getDefaultBoardData();

		/** @var Board $defaultBoard */
		$defaultBoard = $this->boardService->create(
			$boardData['title'] ?? $title,
			$userId,
			$boardData['color'] ?? $color,
		);
		$boardId = $defaultBoard->getId();
		$additionLabels = [];
		$translatedLabelTitles = [
			'Read more inside' => $this->l10n->t('Read more inside'),
		];
		$translatedStackTitles = [
			'Custom lists - click to rename!' => $this->l10n->t('Custom lists - click to rename!'),
			'To Do' => $this->l10n->t('To Do'),
			'In Progress' => $this->l10n->t('In Progress'),
			'Done' => $this->l10n->t('Done'),
		];
		$translatedCardTitles = [
			'1. Open to learn more about boards and cards' => $this->l10n->t('1. Open to learn more about boards and cards'),
			'2. Drag cards left and right, up and down' => $this->l10n->t('2. Drag cards left and right, up and down'),
			'3. Apply rich formatting and link content' => $this->l10n->t('3. Apply rich formatting and link content'),
			'4. Share, comment and collaborate!' => $this->l10n->t('4. Share, comment and collaborate!'),
			'Create your first card!' => $this->l10n->t('Create your first card!'),
		];

		foreach ($boardData['addition_labels'] as $labelData) {
			$additionLabels[] = $this->labelService->create(
				$translatedLabelTitles[$labelData['title']] ?? $labelData['title'],
				$labelData['color'],
				$boardId
			);
		}

		$defaultLabels = array_merge($defaultBoard->getLabels() ?? [], $additionLabels);

		foreach ($boardData['stacks'] as $stackData) {
			$stack = $this->stackService->create(
				$translatedStackTitles[$stackData['title']] ?? $stackData['title'],
				$boardId,
				$stackData['order']
			);

			foreach ($stackData['cards'] as $cardData) {
				$card = $this->cardService->create(
					$translatedCardTitles[$cardData['title']] ?? $cardData['title'],
					$stack->getId(),
					$cardData['type'],
					$cardData['order'],
					$userId,
					$cardData['description'],
				);

				foreach ($defaultLabels as $defaultLabel) {
					if ($defaultLabel && in_array($defaultLabel->getTitle(), $cardData['labels'])) {
						$this->cardService->assignLabel($card->getId(), $defaultLabel->getId());
					}
				}

				if (!empty($cardData['has_example_attachment'])) {
					$this->attachmentService->create($card->getId(), 'file', 'DEFAULT_SAMPLE_FILE');
				}
			}
		}

		return $defaultBoard;
	}
}
