<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Service\LabelService;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;

class LabelController extends Controller {
    private $userId;
    private $labelService;
    public function __construct($appName,
                                IRequest $request,
                                LabelService $labelService,
                                $userId){
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->labelService = $labelService;
    }

    /**
     * @NoAdminRequired
     */
    public function create($title, $color, $boardId) {
        return $this->labelService->create($title, $this->userId, $color, $boardId);
    }
    /**
     * @NoAdminRequired
     */
    public function update($id, $title, $color) {
        return $this->labelService->update($id, $title, $this->userId, $color);
    }
    /**
     * @NoAdminRequired
     */
    public function delete($labelId) {
        return $this->labelService->delete($this->userId, $labelId);
    }

}
