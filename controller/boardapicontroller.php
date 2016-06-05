<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\ApiController as BaseApiController;

class BoardApiController extends BaseApiController {
    private $userId;
    public function __construct($appName,
                                IRequest $request,
                                BoardService $stackService,
                                $userId){
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->boardService = $stackService;
    }
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function index() {
        return new DataResponse($this->boardService->findAll($this->userId));
    }
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function create($title, $color) {
        return new DataResponse($this->boardService->create($title, $this->userId, $color));
    }
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function update($id, $title, $color) {
        return new DataResponse($this->boardService->create($title, $this->userId, $color));
    }
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @CORS
     */
    public function delete($id) {
        return new DataResponse($this->boardService->create($title, $this->userId, $color));
    }
}
