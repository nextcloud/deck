<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Automation;

use OCA\Deck\Db\Card;

interface ActionInterface {
	/**
	 * Execute the action on the given card
	 *
	 * @param Card $card The card to execute the action on
	 * @param AutomationEvent $event The event that triggered the action
	 * @param array $config The action configuration
	 * @throws \Exception If the action fails
	 */
	public function execute(Card $card, AutomationEvent $event, array $config = []): void;

	/**
	 * Validate the action configuration
	 *
	 * @param array $config The configuration to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validateConfig(array $config): bool;

	/**
	 * Check if this action is applicable for the given event
	 * For example, adding labels doesn't make sense on card deletion
	 *
	 * @param AutomationEvent $event The event to check
	 * @return bool True if the action should be executed, false otherwise
	 */
	public function isApplicableForEvent(AutomationEvent $event): bool;

	/**
	 * Get a human-readable description of the action
	 *
	 * @param array $config The action configuration
	 * @return string Description of the action
	 */
	public function getDescription(array $config): string;
}
