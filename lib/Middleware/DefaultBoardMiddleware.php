<?php
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Middleware;

use OCA\Deck\Service\DefaultBoardService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\ILogger;

class DefaultBoardMiddleware extends Middleware {

	/** @var ILogger */
	private $logger;
	/** @var IL10N */
	private $l10n;
	/** @var DefaultBoardService */
	private $defaultBoardService;
	/** @var PermissionService */
	private $permissionService;
	/** @var string|null */
	private $userId;

	public function __construct(ILogger $logger, IL10N $l10n, DefaultBoardService $defaultBoardService, PermissionService $permissionService, $userId) {
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->defaultBoardService = $defaultBoardService;
		$this->permissionService = $permissionService;
		$this->userId = $userId;
	}

	public function beforeController($controller, $methodName) {
		try {
			if ($this->userId !== null && $this->defaultBoardService->checkFirstRun($this->userId) && $this->permissionService->canCreate()) {
				$this->defaultBoardService->createDefaultBoard($this->l10n->t('Personal'), $this->userId, '0087C5');
			}
		} catch (\Throwable $e) {
			$this->logger->logException($e);
		}
	}
}
