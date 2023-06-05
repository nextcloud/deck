<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Deck\Cron;

use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\Service\AttachmentService;
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

	public function __construct(ITimeFactory $time, BoardMapper $boardMapper, CardMapper $cardMapper, AttachmentService $attachmentService, AttachmentMapper $attachmentMapper) {
		parent::__construct($time);
		$this->boardMapper = $boardMapper;
		$this->cardMapper = $cardMapper;
		$this->attachmentService = $attachmentService;
		$this->attachmentMapper = $attachmentMapper;

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
	}
}
