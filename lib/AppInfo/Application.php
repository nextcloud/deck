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

namespace OCA\Deck\AppInfo;

use OCP\AppFramework\App;
use OCA\Deck\Middleware\SharingMiddleware;


class Application extends App {

	/**
	 * Application constructor.
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = array()) {
		parent::__construct('deck', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		// This is currently unused
		$container->registerService('SharingMiddleware', function ($container) use ($server) {
			return new SharingMiddleware(
				$container,
				$server->getRequest(),
				$server->getUserSession(),
				$container->query('ControllerMethodReflector')
			);
		});
		$container->registerMiddleware('SharingMiddleware');

	}

	public function registerNavigationEntry() {
		$container = $this->getContainer();
		$container->query('OCP\INavigationManager')->add(function () use ($container) {
			$urlGenerator = $container->query('OCP\IURLGenerator');
			$l10n = $container->query('OCP\IL10N');
			return [
				'id' => 'deck',
				'order' => 10,
				'href' => $urlGenerator->linkToRoute('deck.page.index'),
				'icon' => $urlGenerator->imagePath('deck', 'app.svg'),
				'name' => $l10n->t('Deck'),
			];
		});
	}
}
