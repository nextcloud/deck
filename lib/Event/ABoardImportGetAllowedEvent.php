<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCA\Deck\Service\Importer\BoardImportService;
use OCP\EventDispatcher\Event;

abstract class ABoardImportGetAllowedEvent extends Event {
	private $service;

	public function __construct(BoardImportService $service) {
		parent::__construct();

		$this->service = $service;
	}

	public function getService(): BoardImportService {
		return $this->service;
	}
}
