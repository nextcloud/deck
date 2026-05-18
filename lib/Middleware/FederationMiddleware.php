<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Middleware;

use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class FederationMiddleware extends Middleware {
	public function __construct(
		private LoggerInterface $logger,
		private PermissionService $permissionService,
		private IRequest $request,
		private ConfigService $configService,
	) {
	}

	public function beforeController($controller, $methodName): void {
		try {
			$this->configService->ensureFederationEnabled();
		} catch (\Exception $e) {
			return;
		}
		$accessToken = $this->request->getHeader('deck-federation-accesstoken');
		if ($accessToken) {
			$this->permissionService->setAccessToken($accessToken);
		}
	}
}
