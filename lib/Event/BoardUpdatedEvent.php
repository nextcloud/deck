<?php
/*
 * @copyright Copyright (c) 2022 chandi Langecker <git@chandi.it>
 *
 * @author chandi Langecker <git@chandi.it>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Event;

use OCP\EventDispatcher\Event;

class BoardUpdatedEvent extends Event {
	private $boardId;
	
	public function __construct(int $boardId) {
		parent::__construct();

		$this->boardId = $boardId;
	}

	public function getBoardId(): int {
		return $this->boardId;
	}
}
