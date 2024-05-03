<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\OverviewService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class OverviewApiController extends OCSController {
	public function __construct(
		$appName,
		IRequest $request,
		private OverviewService $dashboardService,
		private $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function upcomingCards(): DataResponse {
		return new DataResponse($this->dashboardService->findUpcomingCards($this->userId));
	}
}
