<?php

namespace OCA\Deck\Controller;

use OCP\IRequest;
use OCP\AppFramework\ApiController as BaseApiController;

class ApiController extends BaseApiController {
    private $defaults;
    public function __construct($appName,
                                IRequest $request, OC_Defaults $defaults){
        parent::__construct($appName, $request);
        $this->defaults = $defaults;
    }
    /**
     * @PublicPage
     * @NoCSRFRequired
     * @CORS
     */
    public function generateCss() {
        return 
    }
}
