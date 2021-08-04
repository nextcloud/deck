<?php
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

declare(strict_types=1);


namespace OCA\Deck\Service;

use OCA\Circles\Api\v1\Circles;
use OCP\App\IAppManager;

/**
 * Wrapper around circles app API since it is not in a public namespace so we need to make sure that
 * having the app disabled is properly handled
 */
class CirclesService {
	private $circlesEnabled;

	public function __construct(IAppManager $appManager) {
		$this->circlesEnabled = $appManager->isEnabledForUser('circles');
	}

	public function getCircle($circleId) {
		if (!$this->circlesEnabled) {
			return null;
		}

		return \OCA\Circles\Api\v1\Circles::detailsCircle($circleId, true);
	}

	public function isUserInCircle($circleId, $userId): bool {
		if (!$this->circlesEnabled) {
			return false;
		}

		try {
			$member = \OCA\Circles\Api\v1\Circles::getMember($circleId, $userId, 1, true);
			return $member->getLevel() >= Circles::LEVEL_MEMBER;
		} catch (\Exception $e) {
		}
		return false;
	}
}
