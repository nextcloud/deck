<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Validators;

class AssignmentServiceValidator extends BaseValidator {
	public function rules() {
		return [
			'cardId' => ['numeric'],
			'userId' => ['not_empty', 'not_null', 'not_false', 'max:64'],
		];
	}
}
