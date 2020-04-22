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

namespace OCA\Deck\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCA\Deck\Service\LabelService;

/**
 * Class BoardApiController
 *
 * @package OCA\Deck\Controller
 */
class LabelApiController extends ApiController {
	private $labelService;
	private $userId;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param LabelService $labelService
	 * @param $userId
	 */
	public function __construct($appName, IRequest $request, LabelService $labelService, $userId) {
		parent::__construct($appName, $request);
		$this->labelService = $labelService;
		$this->userId = $userId;
	}
	
	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Get a specific label.
	 */
	public function get() {
		$label = $this->labelService->find($this->request->getParam('labelId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * Create a new label
	 */
	public function create($title, $color) {
		$label = $this->labelService->create($title, $color, $this->request->getParam('boardId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @params $title
	 * @params $color
	 * Update a specific label
	 */
	public function update($title, $color) {
		$label = $this->labelService->update($this->request->getParam('labelId'), $title, $color);
		return new DataResponse($label, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * Delete a specific label
	 */
	public function delete() {
		$label = $this->labelService->delete($this->request->getParam('labelId'));
		return new DataResponse($label, HTTP::STATUS_OK);
	}
}
