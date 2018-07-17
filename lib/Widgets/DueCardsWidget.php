<?php declare(strict_types=1);


/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Deck\Widgets;


use OCA\Deck\AppInfo\Application;
use OCP\AppFramework\QueryException;
use OCP\Dashboard\IDashboardWidget;
use OCP\Dashboard\Model\IWidgetRequest;
use OCP\Dashboard\Model\IWidgetSettings;
use OCP\IL10N;


class DueCardsWidget implements IDashboardWidget {

	const WIDGET_ID = 'deck-due-cards';


	/** @var IL10N */
	private $l10n;


	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}


	/**
	 * @return string
	 */
	public function getId(): string {
		return self::WIDGET_ID;
	}


	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Due Cards');
	}


	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->l10n->t('Show cards from the Deck app that are over or near due date');
	}


	/**
	 * @return array
	 */
	public function getTemplate(): array {
		return [
			'app'      => 'deck',
			'icon'     => 'icon-deck',
			'css'      => ['icons', 'widgets'],
			'js'       => 'widgets/DueCards',
			'content'  => 'widgets/dueCards',
			'function' => 'OCA.Deck.dueCards.init'
		];
	}


	/**
	 * @return array
	 */
	public function widgetSetup(): array {
		return [
			'size' => [
				'min'     => [
					'width'  => 4,
					'height' => 3
				],
				'default' => [
					'width'  => 5,
					'height' => 4
				],
				'max'     => [
					'width'  => 10,
					'height' => 8
				]
			],
			'menu' => [
				[
					'icon'     => 'icon-deck',
					'text'     => 'Some option in the menu',
					'function' => 'OCA.Deck.dueCards.menuOption'
				]
			],
			//			'jobs' => [
			//				[
			//					'delay'    => 300,
			//					'function' => 'OCA.Deck.dueCards.cron'
			//				]
			//			],
			'push' => 'OCA.Deck.dueCards.push'
		];
	}


	/**
	 * @param IWidgetSettings $settings
	 */
	public function loadWidget(IWidgetSettings $settings) {
		try {
			$app = new Application();
		} catch (QueryException $e) {
		}

		$container = $app->getContainer();
		try {
//			$this->someService = $container->query(SomeService::class);
		} catch (QueryException $e) {
			return;
		}
	}


	/**
	 * @param IWidgetRequest $request
	 */
	public function requestWidget(IWidgetRequest $request) {
		if ($request->getRequest() === 'getDueCards') {
			$request->addResult(
				'cards', [
				['id' => 1],
				['id' => 2]
			]
			);
//			$request->addResult('cards', $this->someService->getCards());
		}
	}


}