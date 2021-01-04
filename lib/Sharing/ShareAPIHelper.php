<?php
/*
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


namespace OCA\Deck\Sharing;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Share\IShare;

class ShareAPIHelper {
	private $urlGenerator;
	private $timeFactory;
	private $cardMapper;
	private $permissionService;
	private $l10n;

	public function __construct(IURLGenerator $urlGenerator, ITimeFactory $timeFactory, CardMapper $cardMapper, PermissionService $permissionService, IL10N $l10n) {
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;
		$this->cardMapper = $cardMapper;
		$this->permissionService = $permissionService;
		$this->l10n = $l10n;
	}

	public function formatShare(IShare $share): array {
		$result = [];
		$card = $this->cardMapper->find($share->getSharedWith());
		$boardId = $this->cardMapper->findBoardId($card->getId());
		$result['share_with'] = $share->getSharedWith();
		$result['share_with_displayname'] = $card->getTitle();
		$result['share_with_link'] = $this->urlGenerator->linkToRouteAbsolute('deck.page.index') . '#/board/' . $boardId . '/card/' . $card->getId();
		return $result;
	}

	public function createShare(IShare $share, string $shareWith, int $permissions, $expireDate) {
		$share->setSharedWith($shareWith);
		$share->setPermissions($permissions);

		if ($expireDate !== '') {
			try {
				$expireDate = $this->parseDate($expireDate);
				$share->setExpirationDate($expireDate);
			} catch (\Exception $e) {
				throw new OCSNotFoundException($this->l10n->t('Invalid date, date format must be YYYY-MM-DD'));
			}
		}
	}

	/**
	 * Make sure that the passed date is valid ISO 8601
	 * So YYYY-MM-DD
	 * If not throw an exception
	 *
	 * Copied from \OCA\Files_Sharing\Controller\ShareAPIController::parseDate.
	 *
	 * @param string $expireDate
	 * @return \DateTime
	 * @throws \Exception
	 */
	private function parseDate(string $expireDate): \DateTime {
		try {
			$date = $this->timeFactory->getDateTime($expireDate);
		} catch (\Exception $e) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		$date->setTime(0, 0, 0);

		return $date;
	}

	/**
	 * Returns whether the given user can access the given room share or not.
	 *
	 * A user can access a room share only if she is a participant of the room.
	 *
	 * @param IShare $share
	 * @param string $user
	 * @return bool
	 */
	public function canAccessShare(IShare $share, string $user): bool {
		try {
			$this->permissionService->checkPermission($this->cardMapper, $share->getSharedWith(), Acl::PERMISSION_READ, $user);
		} catch (NoPermissionException $e) {
			return false;
		}
		return true;
	}
}
