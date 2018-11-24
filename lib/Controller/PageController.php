<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCA\Deck\Service\DefaultBoardService;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\IL10N;

class PageController extends Controller {

	private $defaultBoardService;
	private $userId;
	private $l10n;

	public function __construct(
		$AppName, 
		IRequest $request,		
		DefaultBoardService $defaultBoardService,
		IL10N $l10n,
		$userId
		) {
		parent::__construct($AppName, $request);

		$this->userId = $userId;
		$this->defaultBoardService = $defaultBoardService;
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
		$params = [
			'user' => $this->userId,
			'maxUploadSize' => (int)\OCP\Util::uploadLimit(),
		];
			
		if ($this->defaultBoardService->checkFirstRun($this->userId, $this->appName)) {
			$this->defaultBoardService->createDefaultBoard($this->l10n->t('Personal'), $this->userId, '000000');
		}

		return new TemplateResponse('deck', 'vue', $params);
	}

}
