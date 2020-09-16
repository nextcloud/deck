<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;

class PageControllerTest extends \Test\TestCase {
	private $controller;
	private $request;
	private $l10n;
	private $permissionService;
	private $initialState;
	private $configService;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->request = $this->createMock(IRequest::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->initialState = $this->createMock(IInitialStateService::class);

		$this->controller = new PageController(
			'deck', $this->request, $this->permissionService, $this->initialState, $this->configService
		);
	}

	public function testIndex() {
		$this->permissionService->expects($this->any())
			->method('canCreate')
			->willReturn(true);

		$response = $this->controller->index();
		$this->assertEquals('main', $response->getTemplateName());
	}
}
