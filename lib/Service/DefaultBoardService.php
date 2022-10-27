<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\BoardMapper;
use OCP\IConfig;
use OCP\IL10N;
use OCA\Deck\BadRequestException;
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
			IConfig $config
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
		$userBoards = $this->boardMapper->findAllByUser($userId);

		if ($firstRun === 'yes' && count($userBoards) === 0) {
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
