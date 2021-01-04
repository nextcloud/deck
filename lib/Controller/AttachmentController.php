<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\AttachmentService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class AttachmentController extends Controller {

	/** @var AttachmentService */
	private $attachmentService;

	public function __construct($appName, IRequest $request, AttachmentService $attachmentService) {
		parent::__construct($appName, $request);
		$this->attachmentService = $attachmentService;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getAll($cardId) {
		return $this->attachmentService->findAll($cardId, true);
	}

	/**
	 * @param $cardId
	 * @param $attachmentId
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return \OCP\AppFramework\Http\Response
	 * @throws \OCA\Deck\NotFoundException
	 */
	public function display($cardId, $attachmentId) {
		if (strpos($attachmentId, ':') === false) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->display($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create($cardId) {
		return $this->attachmentService->create(
			$cardId,
			$this->request->getParam('type'),
			$this->request->getParam('data')
		);
	}

	/**
	 * @NoAdminRequired
	 */
	public function update($cardId, $attachmentId) {
		if (strpos($attachmentId, ':') === false) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->update($cardId, $attachmentId, $this->request->getParam('data'), $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function delete($cardId, $attachmentId) {
		if (strpos($attachmentId, ':') === false) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->delete($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function restore($cardId, $attachmentId) {
		if (strpos($attachmentId, ':') === false) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->restore($cardId, $attachmentId, $type);
	}
}
