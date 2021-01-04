<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Deck;

use OCA\Deck\Service\PermissionService;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {

	/** @var IAppManager */
	private $appManager;
	/**  @var PermissionService */
	private $permissionService;


	public function __construct(IAppManager $appManager, PermissionService $permissionService) {
		$this->appManager = $appManager;
		$this->permissionService = $permissionService;
	}

	/**
	 * Function an app uses to return the capabilities
	 *
	 * @return array Array containing the apps capabilities
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
