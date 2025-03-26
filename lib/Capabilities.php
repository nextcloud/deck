<?php

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck;

use OCA\Deck\Service\PermissionService;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {

	/** @var IAppManager */
	private $appManager;
	/** @var PermissionService */
	private $permissionService;


	public function __construct(IAppManager $appManager, PermissionService $permissionService) {
		$this->appManager = $appManager;
		$this->permissionService = $permissionService;
	}

	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array{deck: array{version: string, canCreateBoards: bool, apiVersions: array<string>}}
	 * @since 8.2.0
	 */
	public function getCapabilities() {
		return [
			'deck' => [
				'version' => $this->appManager->getAppVersion('deck'),
				'canCreateBoards' => $this->permissionService->canCreate(),
				'apiVersions' => [
					'1.0',
					'1.1'
				]
			]
		];
	}
}
