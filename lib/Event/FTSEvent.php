<?php
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Deck\Event;

use OCP\EventDispatcher\Event;

/**
 * This is a class to keep compatibility for currently used events in full text search integration
 */
class FTSEvent extends Event {

	/**
	 * @var array
	 */
	private $arguments;

	public function __construct($subject, $arguments = []) {
		parent::__construct();

		$this->arguments = $arguments;
	}

	public function getArgument($key) {
		if (isset($this->arguments[$key])) {
			return $this->arguments[$key];
		}

		throw new \InvalidArgumentException(sprintf('Argument "%s" not found.', $key));
	}
}
