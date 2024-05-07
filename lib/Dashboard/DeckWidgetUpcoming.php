<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

class DeckWidgetUpcoming implements IAPIWidget, IButtonWidget, IIconWidget {
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
