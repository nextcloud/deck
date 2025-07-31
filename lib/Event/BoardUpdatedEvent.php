<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Db\Board;

class BoardUpdatedEvent extends ABoardEvent
{
	private Board $boardBefore;

	public function __construct(Board $board, Board $boardBefore)
	{
		parent::__construct($board);

		$this->boardBefore = $boardBefore;
	}

	public function getBoardBefore(): Board
	{
		return $this->boardBefore;
	}
}
