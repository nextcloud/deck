<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardService;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;

class BoardController extends Controller {
    private $userId;
    private $boardService;

    public function __construct($appName,
                                IRequest $request,
                                BoardService $cardService,
                                $userId) {
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->boardService = $cardService;
    }

    /**
     * @NoAdminRequired
     */
    public function index() {

        return $this->boardService->findAll($this->userId);
    }

    /**
     * @NoAdminRequired
     */
    public function read($boardId) {
        // FIXME: Remove as this is just for testing if loading animation works out nicely
        //usleep(2000);
        return $this->boardService->find($this->userId, $boardId);
    }

    /**
     * @NoAdminRequired
     */
    public function create($title, $color) {
        return $this->boardService->create($title, $this->userId, $color);
    }

    /**
     * @NoAdminRequired
     */
    public function update($id, $title, $color) {
        return $this->boardService->update($id, $title, $this->userId, $color);
    }

    /**
     * @NoAdminRequired
     */
    public function delete($boardId) {
        return $this->boardService->delete($this->userId, $boardId);
    }

    public function labels($boardId) {
        return $this->boardService->labels($this->boardId);
    }

    public function addAcl($boardId, $type, $participant, $write, $invite, $manage) {
        return $this->boardService->addAcl($boardId, $type, $participant, $write, $invite, $manage);
    }
    public function updateAcl($id, $permissionWrite, $permissionInvite, $permissionManage) {
        return $this->boardService->updateAcl($id, $permissionWrite, $permissionInvite, $permissionManage);
    }
    public function deleteAcl($id) {
        return $this->boardService->deleteAcl($id);
    }

}