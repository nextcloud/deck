<?php

/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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

namespace OCA\Deck\Controller\Helper;

use OCP\AppFramework\Http;

class ApiHelper {

	public static function boardHasError($boardId, $boardService) {				
		if (is_numeric($boardId) === false) {
			$error['message'] = 'board id must be a number';
			$error['status'] = HTTP::STATUS_BAD_REQUEST;
			return $error;
		}

		$board = $boardService->find($boardId);

		if ($board === false || $board === null) {
			$error['message'] = 'board does not exist';
			$error['status'] = HTTP::STATUS_NOT_FOUND;
			return $error;
		}

		return false;
	}

	public static function stackHasError($stackId, $stackService) {
		if (is_numeric($stackId) === false) {
			$error['message'] = 'board id must be a number';
			$error['status'] = HTTP::STATUS_BAD_REQUEST;
			return $error;
		}

		$stack = $stackService->find($stackId);

		if ($stack === false || $stack === null) {
			$error['message'] = 'stack does not exist';
			$error['status'] = HTTP::STATUS_NOT_FOUND;
			return $error;
		}

		return false;
	}

}