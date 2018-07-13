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

	public static function entityHasError($entityId, $entityName, $service) {				
		if (is_numeric($entityId) === false) {
			$error['message'] = $entityName . ' id must be a number';
			$error['status'] = HTTP::STATUS_BAD_REQUEST;
			return $error;
		}

		$entity = $service->find($entityId);

		if ($entity === false || $entity === null) {
			$error['message'] = $entityName . ' does not exist';
			$error['status'] = HTTP::STATUS_NOT_FOUND;
			return $error;
		}

		return false;
	}	

}