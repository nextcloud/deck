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
use OCA\Deck\Service\AttachmentService;

class AttachmentApiController extends ApiController {
	private $attachmentService;

	public function __construct($appName, IRequest $request, AttachmentService $attachmentService) {
		parent::__construct($appName, $request);
		$this->attachmentService = $attachmentService;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
	public function getAll($apiVersion) {
		$attachment = $this->attachmentService->findAll($this->request->getParam('cardId'), true);
		if ($apiVersion === '1.0') {
			$attachment = array_filter($attachment, function ($attachment) {
				return $attachment->getType() === 'deck_file';
			});
		}
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
	public function display($cardId, $attachmentId, $type = 'deck_file') {
		return $this->attachmentService->display($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
	public function create($cardId, $type, $data) {
		$attachment = $this->attachmentService->create($cardId, $type, $data);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
	public function update($cardId, $attachmentId, $data, $type = 'deck_file') {
		$attachment = $this->attachmentService->update($cardId, $attachmentId, $data, $type);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
	public function delete($cardId, $attachmentId, $type = 'deck_file') {
		$attachment = $this->attachmentService->delete($cardId, $attachmentId, $type);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
	public function restore($cardId, $attachmentId, $type = 'deck_file') {
		$attachment = $this->attachmentService->restore($cardId, $attachmentId, $type);
		return new DataResponse($attachment, HTTP::STATUS_OK);
	}
}
