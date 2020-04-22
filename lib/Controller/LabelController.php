<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\LabelService;
use OCP\IRequest;
use OCP\AppFramework\Controller;

class LabelController extends Controller {
	private $labelService;

	public function __construct($appName, IRequest $request, LabelService $labelService) {
		parent::__construct($appName, $request);
		$this->labelService = $labelService;
	}

	/**
	 * @NoAdminRequired
	 * @param $title
	 * @param $color
	 * @param $boardId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function create($title, $color, $boardId) {
		return $this->labelService->create($title, $color, $boardId);
	}

	/**
	 * @NoAdminRequired
	 * @param $id
	 * @param $title
	 * @param $color
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function update($id, $title, $color) {
		return $this->labelService->update($id, $title, $color);
	}

	/**
	 * @NoAdminRequired
	 * @param $labelId
	 * @return \OCP\AppFramework\Db\Entity
	 */
	public function delete($labelId) {
		return $this->labelService->delete($labelId);
	}
}
