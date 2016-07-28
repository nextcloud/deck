<?php

namespace OCA\Deck\Controller;

use OCP\IRequest;
use OCP\AppFramework\ApiController as BaseApiController;

class StyleController extends Controller {
    private $defaults;
    public function __construct($appName,
                                IRequest $request, OC_Defaults $defaults){
        parent::__construct($appName, $request);
        $this->defaults = $defaults;
    }
    /**
     * @PublicPage
     * @NoCSRFRequired
     */
    public function generateCss() {

        $color = $this->config->getAppValue($this->appName, 'color');
        $responseCss .= '';
        $response = new Http\DataDownloadResponse($responseCss, 'style', 'text/css');
    }
}
