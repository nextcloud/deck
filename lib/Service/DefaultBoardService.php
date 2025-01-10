<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
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

	public function __construct(
		IL10N $l10n,
		BoardMapper $boardMapper,
		BoardService $boardService,
		StackService $stackService,
		CardService $cardService,
		IConfig $config,
	) {
		$this->boardService = $boardService;
		$this->stackService = $stackService;
		$this->cardService = $cardService;
		$this->config = $config;
		$this->boardMapper = $boardMapper;
		$this->l10n = $l10n;
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

	/**
	 * @param $title
	 * @param $userId
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCA\Deck\StatusException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function createDefaultBoard(string $title, string $userId, string $color) {
		$defaultBoard = $this->boardService->create($title, $userId, $color);
		$defaultStacks = [];
		$defaultCards = [];

		$boardId = $defaultBoard->getId();

		$defaultStacks[] = $this->stackService->create($this->l10n->t('To do'), $boardId, 1);
		$defaultStacks[] = $this->stackService->create($this->l10n->t('Doing'), $boardId, 1);
		$defaultStacks[] = $this->stackService->create($this->l10n->t('Done'), $boardId, 1);

		$defaultCards[] = $this->cardService->create($this->l10n->t('Example Task 3'), $defaultStacks[0]->getId(), 'text', 0, $userId);
		$defaultCards[] = $this->cardService->create($this->l10n->t('Example Task 2'), $defaultStacks[1]->getId(), 'text', 0, $userId);
		$defaultCards[] = $this->cardService->create($this->l10n->t('Example Task 1'), $defaultStacks[2]->getId(), 'text', 0, $userId);

		return $defaultBoard;
	}
}
