<?php
/**
 * ownCloud - deck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @copyright Julius Härtl 2016
 */

namespace OCA\Deck\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IL10N;

class PageController extends Controller {


	private $userId;
  private $l10n;

	public function __construct($AppName, IRequest $request,IL10N $l10n, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
    $this->l10n = $l10n;
	}

	/**
	 * Handle main html view from templates/main.php
	 * This will return the main angular application
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$params = ['user' => $this->userId];
		return new TemplateResponse('deck', 'main', $params);
	}

}
