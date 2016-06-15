<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Service\StackService;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;

class StackController extends Controller {
    private $userId;
    private $stackService;
    public function __construct($appName,
                                IRequest $request,
                                StackService $cardService,
                                $userId){
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->stackService = $cardService;
    }
    /**
     * @NoAdminRequired
     */
    public function index($boardId) {
            return $this->stackService->findAll($boardId);
    }
    /**
     * @NoAdminRequired
     */
    public function read($boardId) {
        return $this->stackService->find($this->userId, $boardId);
    }
    /**
     * @NoAdminRequired
     */
    public function create($title, $boardId, $order=999) {
        return $this->stackService->create($title, $boardId, $order);
    }
    /**
     * @NoAdminRequired
     */
    public function update($id, $title, $boardId, $order) {
        return $this->stackService->update($id, $title, $boardId, $order);
    }
    /**
     * @NoAdminRequired
     */
    public function delete($stackId) {
        return $this->stackService->delete($this->userId, $stackId);
    }
}
