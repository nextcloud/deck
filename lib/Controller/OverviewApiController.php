<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
 *
 * @author Jakob Röhrl <jakob.roehrl@web.de>
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

namespace OCA\Deck\Controller;

use OCA\Deck\Service\OverviewService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class OverviewApiController extends OCSController {

	/** @var OverviewService */
	private $dashboardService;

	/** @var string */
	private $userId;

	public function __construct($appName, IRequest $request, OverviewService $dashboardService, $userId) {
		parent::__construct($appName, $request);
		$this->dashboardService = $dashboardService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function upcomingCards(): DataResponse {
		return new DataResponse($this->dashboardService->findUpcomingCards($this->userId));
	}
}
