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

/**
 * Created by PhpStorm.
 * User: jus
 * Date: 16.05.17
 * Time: 12:34
 */

namespace OCA\Deck\Cron;

use OC\BackgroundJob\Job;
use OCA\Deck\Db\BoardMapper;

class DeleteCron extends Job {

	/** @var BoardMapper */
	private $boardMapper;

	public function __construct(BoardMapper $boardMapper) {
		$this->boardMapper = $boardMapper;
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
	}

}