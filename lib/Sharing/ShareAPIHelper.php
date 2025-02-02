<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Sharing;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\NotFoundException;
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
		try {
			$card = $this->cardMapper->find($share->getSharedWith());
		} catch (DoesNotExistException $e) {
			throw new NotFoundException($e->getMessage());
		}
		$boardId = $this->cardMapper->findBoardId($card->getId());
		$result['share_with'] = $share->getSharedWith();
		$result['share_with_displayname'] = $card->getTitle();
		$result['share_with_link'] = $this->urlGenerator->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $card->getId()]);
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
			$this->permissionService->checkPermission($this->cardMapper, (int)$share->getSharedWith(), Acl::PERMISSION_READ, $user);
		} catch (NoPermissionException $e) {
			return false;
		}
		return true;
	}
}
