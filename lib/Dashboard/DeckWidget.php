<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Dashboard;

use DateTime;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Label;
use OCA\Deck\Service\OverviewService;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class DeckWidget implements IAPIWidget, IButtonWidget, IIconWidget {
	private IL10N $l10n;
	private OverviewService $dashboardService;
	private IURLGenerator $urlGenerator;
	private IDateTimeFormatter $dateTimeFormatter;

	public function __construct(IL10N $l10n,
		OverviewService $dashboardService,
		IDateTimeFormatter $dateTimeFormatter,
		IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->dashboardService = $dashboardService;
		$this->urlGenerator = $urlGenerator;
		$this->dateTimeFormatter = $dateTimeFormatter;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'deck';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Upcoming cards');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-deck';
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'deck-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addScript('deck', 'deck-dashboard');
	}

	/**
	 * @inheritDoc
	 */
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$upcomingCards = $this->dashboardService->findUpcomingCards($userId);
		$nowTimestamp = (new Datetime())->getTimestamp();
		$sinceTimestamp = $since !== null ? (new Datetime($since))->getTimestamp() : null;
		$upcomingCards = array_filter($upcomingCards, static function (array $card) use ($nowTimestamp, $sinceTimestamp) {
			if (isset($card['duedate'])) {
				$ts = (new Datetime($card['duedate']))->getTimestamp();
				return $ts > $nowTimestamp && ($sinceTimestamp === null || $ts > $sinceTimestamp);
			}
			return false;
		});
		usort($upcomingCards, static function ($a, $b) {
			$a = new Datetime($a['duedate']);
			$ta = $a->getTimestamp();
			$b = new Datetime($b['duedate']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? 1 : -1;
		});
		$upcomingCards = array_slice($upcomingCards, 0, $limit);
		$urlGenerator = $this->urlGenerator;
		$dateTimeFormatter = $this->dateTimeFormatter;
		return array_map(static function (array $card) use ($urlGenerator, $dateTimeFormatter) {
			$formattedDueDate = $dateTimeFormatter->formatDateTime(new DateTime($card['duedate']));
			return new WidgetItem(
				$card['title'] . ' (' . $formattedDueDate . ')',
				implode(
					', ',
					array_map(static function (Label $label) {
						return $label->jsonSerialize()['title'];
					}, $card['labels'])
				),
				$urlGenerator->getAbsoluteURL(
					$urlGenerator->linkToRoute(Application::APP_ID . '.page.redirectToCard', ['cardId' => $card['id']])
				),
				$urlGenerator->getAbsoluteURL(
					$urlGenerator->imagePath(Application::APP_ID, 'deck-dark.svg')
				),
				$card['duedate']
			);
		}, $upcomingCards);
	}

	/**
	 * @inheritDoc
	 */
	public function getWidgetButtons(string $userId): array {
		return [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index')
				),
				$this->l10n->t('Load more')
			),
		];
	}
}
