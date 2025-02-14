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
use Psr\Log\LoggerInterface;

class DefaultBoardMiddleware extends Middleware {

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private DefaultBoardService $defaultBoardService,
		private PermissionService $permissionService,
		private ?string $userId,
	) {
	}

	public function beforeController($controller, $methodName) {
		try {
			if ($this->userId !== null && $this->defaultBoardService->checkFirstRun($this->userId) && $this->permissionService->canCreate()) {
				$this->defaultBoardService->createDefaultBoard($this->l10n->t('Welcome to Nextcloud Deck!'), $this->userId, 'bf678b');
			}
		} catch (\Throwable $e) {
			$this->logger->error('Could not create default board', ['exception' => $e]);
		}
	}
}
