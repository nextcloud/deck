<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Label;
use OCA\Deck\Service\LabelService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;

class LabelController extends Controller {
	public function __construct(
		$appName,
		IRequest $request,
		private LabelService $labelService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function create(string $title, string $color, int $boardId): Label {
		return $this->labelService->create($title, $color, $boardId);
	}

	#[NoAdminRequired]
	public function update(int $id, string $title, string $color): Label {
		return $this->labelService->update($id, $title, $color);
	}

	#[NoAdminRequired]
	public function delete(int $labelId): Label {
		return $this->labelService->delete($labelId);
	}
}
