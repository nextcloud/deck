<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Validators;

class LabelServiceValidator extends BaseValidator {
	protected function hex_color(string $value): bool {
		return preg_match('/[A-Fa-f0-9]{6}/', $value) === 1;
	}

	public function rules() {
		return [
			'id' => ['numeric'],
			'title' => ['not_empty', 'not_null', 'not_false', 'max:100'],
			'boardId' => ['numeric', 'not_null'],
			'color' => ['not_empty', 'not_null', 'not_false', 'max:6', 'hex_color']
		];
	}
}
