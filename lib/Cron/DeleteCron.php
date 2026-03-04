<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Deck\Cron;

use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Sharing\DeckShareProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class DeleteCron extends TimedJob {

	/** @var BoardMapper */
	private $boardMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var AttachmentService */
	private $attachmentService;
	/** @var AttachmentMapper */
	private $attachmentMapper;
	/** @var StackMapper */
	private $stackMapper;
	/** @var DeckShareProvider */
	private $deckShareProvider;

	public function __construct(
		ITimeFactory $time,
		BoardMapper $boardMapper,
		CardMapper $cardMapper,
		AttachmentService $attachmentService,
		AttachmentMapper $attachmentMapper,
		StackMapper $stackMapper,
		DeckShareProvider $deckShareProvider,
	) {
		parent::__construct($time);
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->attachmentService = $attachmentService;
		$this->attachmentMapper = $attachmentMapper;
		$this->stackMapper = $stackMapper;
		$this->deckShareProvider = $deckShareProvider;

		$this->setInterval(60 * 60 * 24);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/**
	 * @param $argument
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function run($argument) {
		$boards = $this->boardMapper->findToDelete();
		foreach ($boards as $board) {
			$this->boardMapper->delete($board);
		}

		$timeLimit = time() - (60 * 5); // 5 min buffer
		$cards = $this->cardMapper->findToDelete($timeLimit, 500);
		foreach ($cards as $card) {
			$this->cardMapper->delete($card);
		}

		$attachments = $this->attachmentMapper->findToDelete();
		foreach ($attachments as $attachment) {
			try {
				$service = $this->attachmentService->getService($attachment->getType());
				$service->delete($attachment);
			} catch (InvalidAttachmentType $e) {
				// Just delete the attachment if no service is available
			}
			$this->attachmentMapper->delete($attachment);
		}

		// Delete orphaned attachment shares
		$shares = $this->deckShareProvider->getOrphanedAttachmentShares();
		foreach ($shares as $share) {
			$this->deckShareProvider->delete($share);
		}

		$stacks = $this->stackMapper->findToDelete();
		foreach ($stacks as $stack) {
			$this->stackMapper->delete($stack);
		}
	}
}
