<?php
/**
 * @copyright Copyright (c) 2022 Raul Ferreira Fuentes <raul@nextcloud.com>
 *
 * @author Raul Ferreira Fuentes <raul@nextcloud.com>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Deck\Model;

use OCA\Deck\Db\Board;

class BoardSummary extends Board {
	private Board $board;

	public function __construct(Board $board) {
		parent::__construct();
		$this->board = $board;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle()
		];
	}

	public function __call($name, $arguments) {
		return $this->board->__call($name, $arguments);
	}
}
