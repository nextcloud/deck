<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\LabelService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class LabelController extends Controller {
	public function __construct(
		$appName,
		IRequest $request,
		private LabelService $labelService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $color
	 * @param $boardId
	 * @param array<string, scalar> $customSettings
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $color, $boardId, array $customSettings = []) {
		return $this->labelService->create($title, $color, $boardId, $customSettings);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $color
	 * @param array<string, scalar> $customSettings
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $color, array $customSettings = []) {
		return $this->labelService->update($id, $title, $color, $customSettings);
	}

	/**
	 * @NoAdminRequired
	 * @param $labelId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($labelId) {
		return $this->labelService->delete($labelId);
	}
}
