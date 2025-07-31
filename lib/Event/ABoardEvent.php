<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardService;
use OCP\EventDispatcher\Event;
use OCP\Server;

class ABoardEvent extends Event
{
	private Board $board;

	public function __construct(Board|int $board) {
		parent::__construct();

		// TODO: Can be altered when `getBoardId` function is removed, make sure parameter is also set correctly
		$this->board = is_int($board) ? Server::get(BoardService::class)->find($board) : $board;
	}

	public function getBoard(): Board
	{
		return $this->board;
	}

	/**
	 * @deprecated Will be removed soon
	 */
	public function getBoardId(): int
	{
		return $this->board->getId();
	}
}
