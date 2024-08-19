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
use Psr\Log\LoggerInterface;

class SessionsCleanup extends TimedJob {
	private $documentService;
	private $imageService;


	public function __construct(
		ITimeFactory $time,
		private SessionService $sessionService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(SessionService::SESSION_VALID_TIME);
	}

	protected function run($argument) {
		$this->logger->debug('Run cleanup job for deck sessions');
		
		$removedSessions = $this->sessionService->removeInactiveSessions();
		$this->logger->debug('Removed ' . $removedSessions . ' inactive sessions');
	}
}
