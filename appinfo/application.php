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

namespace OCA\Deck\AppInfo;

use OCP\AppFramework\App;
use OCA\Deck\Middleware\SharingMiddleware;


class Application extends App {
	public function __construct(array $urlParams = array()) {
		parent::__construct('deck', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('SharingMiddleware', function ($c) use ($server) {
			return new SharingMiddleware(
				$server->getUserManager(),
				$server->getGroupManager(),
				$server->getDatabaseConnection(),
				$server->getUserSession()->getUser()->getUID()
			);
		});
		$container->registerMiddleware('SharingMiddleware');


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