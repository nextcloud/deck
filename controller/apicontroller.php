<?php

namespace OCA\Deck\Controller;

use OCP\IRequest;
use OCP\AppFramework\ApiController as BaseApiController;

class ApiController extends BaseApiController {
    public function __construct($appName,
                                IRequest $request){
        parent::__construct($appName, $request);
    }
    /**
     * @PublicPage
     * @NoCSRFRequired
     * @CORS
     */
    public function index() {
        return [
            'apiLevels' => ['v1']
        ];
    }
}
