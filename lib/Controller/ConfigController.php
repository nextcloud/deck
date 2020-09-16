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

namespace OCA\Deck\Controller;

use OCA\Deck\Service\ConfigService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class ConfigController extends OCSController {
	private $configService;

	public function __construct(
		$AppName,
		IRequest $request,
		ConfigService $configService
		) {
		parent::__construct($AppName, $request);

		$this->configService = $configService;
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function get(): DataResponse {
		return new DataResponse($this->configService->getAll());
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function setValue(string $key, $value) {
		$result = $this->configService->set($key, $value);
		if ($result === null) {
			return new NotFoundResponse();
		}
		return new DataResponse($result);
	}
}
