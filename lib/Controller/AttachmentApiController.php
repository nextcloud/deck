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
    public function getAll() {
        $attachment = $this->attachmentService->findAll($this->request->getParam('cardId'));
        return new DataResponse($attachment, HTTP::STATUS_OK);
    }

    /**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
    public function display() {
        $attachment = $this->attachmentService->display($this->request->getParam('cardId'), $this->request->getParam('attachmentId'));
        return new DataResponse($attachment, HTTP::STATUS_OK);
    }

    /**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
    public function create($type, $data) {
        $attachment = $this->attachmentService->create($this->request->getParam('cardId'), $type, $data);
        return new DataResponse($attachment, HTTP::STATUS_OK);
    }

    /**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
    public function update($data) {
        $attachment = $this->attachmentService->update($this->request->getParam('cardId'), $this->request->getParam('attachmentId'), $data);
        return new DataResponse($attachment, HTTP::STATUS_OK);
    }

    /**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
    public function delete() {
        $attachment = $this->attachmentService->delete($this->request->getParam('cardId'), $this->request->getParam('attachmentId'));
        return new DataResponse($attachment, HTTP::STATUS_OK);
    }

    /**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 */
    public function restore() {
        $attachment = $this->attachmentService->restore($this->request->getParam('cardId'), $this->request->getParam('attachmentId'));
        return new DataResponse($attachment, HTTP::STATUS_OK);
    }
}