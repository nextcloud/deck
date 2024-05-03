<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
