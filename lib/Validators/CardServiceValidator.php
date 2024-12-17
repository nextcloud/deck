<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Validators;

class CardServiceValidator extends BaseValidator {
	public function rules() {
		return [
			'id' => ['numeric'],
			'title' => ['not_empty', 'not_null', 'not_false', 'max:255'],
			'cardId' => ['numeric'],
			'stackId' => ['numeric'],
			'boardId' => ['numeric'],
			'labelId' => ['numeric'],
			'type' => ['not_empty', 'not_null', 'not_false', 'max:64'],
			'order' => ['numeric'],
			'owner' => ['not_empty', 'not_null', 'not_false', 'max:64'],
		];
	}
}
