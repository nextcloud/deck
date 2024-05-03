<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */



namespace OCA\Deck\Cron;

use OCA\Deck\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;

class SessionsCleanup extends TimedJob {
	private $sessionService;
	private $documentService;
	private $logger;
	private $imageService;


	public function __construct(ITimeFactory $time,
		SessionService $sessionService,
		ILogger $logger) {
		parent::__construct($time);
		$this->sessionService = $sessionService;
		$this->logger = $logger;
		$this->setInterval(SessionService::SESSION_VALID_TIME);
	}

	protected function run($argument) {
		$this->logger->debug('Run cleanup job for deck sessions');
		
		$removedSessions = $this->sessionService->removeInactiveSessions();
		$this->logger->debug('Removed ' . $removedSessions . ' inactive sessions');
	}
}
