<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;

class DeckWidgetToday implements IWidget {

	/**
	 *
	 * @var IL10N
	 */
	private $l10n;

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'deckToday';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Cards due today');
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
	public function getUrl(): ?string {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		\OCP\Util::addScript('deck', 'deck-dashboard');
	}
}
