<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\NoPermissionException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUserSession;

class ConfigService {
	public const SETTING_BOARD_NOTIFICATION_DUE_OFF = 'off';
	public const SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED = 'assigned';
	public const SETTING_BOARD_NOTIFICATION_DUE_ALL = 'all';
	public const SETTING_BOARD_NOTIFICATION_DUE_DEFAULT = self::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED;

	private IConfig $config;
	private ?string $userId = null;
	private IGroupManager $groupManager;

	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
	) {
		$this->groupManager = $groupManager;
		$this->config = $config;
	}

	public function getUserId(): ?string {
		if (!$this->userId) {
			// We cannot use DI for the userId or UserSession as the ConfigService
			// is initiated too early before the session is actually loaded
			$user = \OCP\Server::get(IUserSession::class)->getUser();
			$this->userId = $user ? $user->getUID() : null;
		}

		return $this->userId;
	}

	public function getAll(): array {
		if ($this->getUserId() === null) {
			return [];
		}

		$data = [
			'calendar' => $this->isCalendarEnabled(),
			'cardDetailsInModal' => $this->isCardDetailsInModal(),
			'cardIdBadge' => $this->isCardIdBadgeEnabled()
		];
		if ($this->groupManager->isAdmin($this->getUserId())) {
			$data['groupLimit'] = $this->get('groupLimit');
		}
		return $data;
	}

	/**
	 * @return bool|array{id: string, displayname: string}[]
	 * @throws NoPermissionException
	 */
	public function get(string $key) {
		[$scope] = explode(':', $key, 2);
		switch ($scope) {
			case 'groupLimit':
				if ($this->getUserId() === null || !$this->groupManager->isAdmin($this->getUserId())) {
					throw new NoPermissionException('You must be admin to get the group limit');
				}
				return $this->getGroupLimit();
			case 'calendar':
				if ($this->getUserId() === null) {
					return false;
				}
				return (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'calendar', true);
			case 'cardDetailsInModal':
				if ($this->getUserId() === null) {
					return false;
				}
				return (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'cardDetailsInModal', true);
			case 'cardIdBadge':
				if ($this->getUserId() === null) {
					return false;
				}
				return (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'cardIdBadge', false);
		}
		return false;
	}

	public function isCalendarEnabled(?int $boardId = null): bool {
		if ($this->getUserId() === null) {
			return false;
		}

		$appConfigState = $this->config->getAppValue(Application::APP_ID, 'calendar', 'yes') === 'yes';
		$defaultState = (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'calendar', $appConfigState);
		if ($boardId === null) {
			return $defaultState;
		}

		return (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'board:' . $boardId . ':calendar', $defaultState);
	}

	public function isCardDetailsInModal(?int $boardId = null): bool {
		if ($this->getUserId() === null) {
			return false;
		}

		$defaultState = (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'cardDetailsInModal', true);
		if ($boardId === null) {
			return $defaultState;
		}

		return (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'board:' . $boardId . ':cardDetailsInModal', $defaultState);
	}

	public function isCardIdBadgeEnabled(): bool {
		if ($this->getUserId() === null) {
			return false;
		}
		$appConfigState = $this->config->getAppValue(Application::APP_ID, 'cardIdBadge', 'yes') === 'no';
		$defaultState = (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'cardIdBadge', $appConfigState);

		return (bool)$this->config->getUserValue($this->getUserId(), Application::APP_ID, 'cardIdBadge', $defaultState);
	}

	public function set($key, $value) {
		if ($this->getUserId() === null) {
			throw new NoPermissionException('Must be logged in to set user config');
		}

		$result = null;
		[$scope] = explode(':', $key, 2);
		switch ($scope) {
			case 'groupLimit':
				if (!$this->groupManager->isAdmin($this->getUserId())) {
					throw new NoPermissionException('You must be admin to set the group limit');
				}
				$result = $this->setGroupLimit($value);
				break;
			case 'calendar':
				$this->config->setUserValue($this->getUserId(), Application::APP_ID, 'calendar', (string)$value);
				$result = $value;
				break;
			case 'cardDetailsInModal':
				$this->config->setUserValue($this->getUserId(), Application::APP_ID, 'cardDetailsInModal', (string)$value);
				$result = $value;
				break;
			case 'cardIdBadge':
				$this->config->setUserValue($this->getUserId(), Application::APP_ID, 'cardIdBadge', (string)$value);
				$result = $value;
				break;
			case 'board':
				[$boardId, $boardConfigKey] = explode(':', $key);
				if ($boardConfigKey === 'notify-due' && !in_array($value, [self::SETTING_BOARD_NOTIFICATION_DUE_ALL, self::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED, self::SETTING_BOARD_NOTIFICATION_DUE_OFF], true)) {
					throw new BadRequestException('Board notification option must be one of: off, assigned, all');
				}
				$this->config->setUserValue($this->getUserId(), Application::APP_ID, $key, (string)$value);
				$result = $value;
		}
		return $result;
	}

	/**
	 * @return string[]
	 */
	private function setGroupLimit(array $value): array {
		$groups = [];
		foreach ($value as $group) {
			$groups[] = $group['id'];
		}
		$data = implode(',', $groups);
		$this->config->setAppValue(Application::APP_ID, 'groupLimit', $data);
		return $groups;
	}

	private function getGroupLimitList(): array {
		$value = $this->config->getAppValue(Application::APP_ID, 'groupLimit', '');
		$groups = explode(',', $value);
		if ($value === '') {
			return [];
		}
		return $groups;
	}

	/** @return array{id: string, displayname: string}[] */
	private function getGroupLimit() {
		$groups = $this->getGroupLimitList();
		$groups = array_map(function (string $groupId): ?array {
			/** @var IGroup $groups */
			$group = $this->groupManager->get($groupId);
			if ($group === null) {
				return null;
			}
			return [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
			];
		}, $groups);
		return array_filter($groups);
	}

	public function getAttachmentFolder(?string $userId = null): string {
		if ($userId === null && $this->getUserId() === null) {
			throw new NoPermissionException('Must be logged in get the attachment folder');
		}

		return $this->config->getUserValue($userId ?? $this->getUserId(), 'deck', 'attachment_folder', '/Deck');
	}

	public function setAttachmentFolder(?string $userId, string $path): void {
		if ($userId === null && $this->getUserId() === null) {
			throw new NoPermissionException('Must be logged in get the attachment folder');
		}

		$this->config->setUserValue($userId ?? $this->getUserId(), 'deck', 'attachment_folder', $path);
	}
}
