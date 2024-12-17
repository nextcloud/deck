<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

abstract class SettingBase extends ActivitySettings {

	/** @var IL10N */
	protected $l;

	/**
	 * @param IL10N $l
	 */
	public function __construct(IL10N $l) {
		$this->l = $l;
	}

	public function getGroupIdentifier() {
		return 'deck';
	}

	public function getGroupName() {
		return $this->l->t('Deck');
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier
	 * @since 11.0.0
	 */
	public function getIdentifier(): string {
		return 'deck';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName(): string {
		return $this->l->t('Changes in the <strong>Deck app</strong>');
	}

	/**
	 * @return int whether the filter should be rather on the top or bottom of
	 *             the admin section. The filters are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 * @since 11.0.0
	 */
	public function getPriority(): int {
		return 90;
	}

	/**
	 * @return bool True when the option can be changed for the stream
	 * @since 11.0.0
	 */
	public function canChangeStream(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the stream
	 * @since 11.0.0
	 */
	public function isDefaultEnabledStream(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the mail
	 * @since 11.0.0
	 */
	public function canChangeMail(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the stream
	 * @since 11.0.0
	 */
	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
