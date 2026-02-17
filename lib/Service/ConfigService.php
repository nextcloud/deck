<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Exceptions\FederationDisabledException;
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
	public const SETTING_CALDAV_LIST_MODE_ROOT_TASKS = 'root_tasks';
	public const SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR = 'per_list_calendar';
	public const SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY = 'list_as_category';
	public const SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY = 'list_as_priority';
	public const SETTING_CALDAV_LIST_MODE_DEFAULT = self::SETTING_CALDAV_LIST_MODE_ROOT_TASKS;

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
		$userId = $this->getUserId();
		if ($userId === null) {
			return [];
		}

		$data = [
			'calendar' => $this->isCalendarEnabled(),
			'cardDetailsInModal' => $this->isCardDetailsInModal(),
			'cardIdBadge' => $this->isCardIdBadgeEnabled(),
			'caldavListMode' => $this->getCalDavListMode(),
		];
		if ($this->groupManager->isAdmin($userId)) {
			$data['groupLimit'] = $this->get('groupLimit');
			$data['federationEnabled'] = $this->get('federationEnabled');
		}
		return $data;
	}

	/**
	 * @return bool|string|array{id: string, displayname: string}[]
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
			case 'federationEnabled':
				return $this->config->getAppValue(Application::APP_ID, 'federationEnabled', 'no') === 'yes';
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
			case 'caldavListMode':
				return $this->getCalDavListMode();
		}
		return false;
	}

	public function getCalDavListMode(): string {
		$userId = $this->getUserId();
		if ($userId === null) {
			return self::SETTING_CALDAV_LIST_MODE_DEFAULT;
		}

		$value = (string)$this->config->getUserValue($userId, Application::APP_ID, 'caldavListMode', self::SETTING_CALDAV_LIST_MODE_DEFAULT);
		$allowed = [
			self::SETTING_CALDAV_LIST_MODE_ROOT_TASKS,
			self::SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR,
			self::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY,
			self::SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY,
		];

		return in_array($value, $allowed, true) ? $value : self::SETTING_CALDAV_LIST_MODE_DEFAULT;
	}

	public function isCalendarEnabled(?int $boardId = null): bool {
		$userId = $this->getUserId();
		if ($userId === null) {
			return false;
		}

		$appConfigState = $this->config->getAppValue(Application::APP_ID, 'calendar', 'yes') === 'yes';
		$defaultState = (bool)$this->config->getUserValue($userId, Application::APP_ID, 'calendar', $appConfigState);
		if ($boardId === null) {
			return $defaultState;
		}

		return (bool)$this->config->getUserValue($userId, Application::APP_ID, 'board:' . $boardId . ':calendar', $defaultState);
	}

	public function isCardDetailsInModal(?int $boardId = null): bool {
		$userId = $this->getUserId();
		if ($userId === null) {
			return false;
		}

		$defaultState = (bool)$this->config->getUserValue($userId, Application::APP_ID, 'cardDetailsInModal', true);
		if ($boardId === null) {
			return $defaultState;
		}

		return (bool)$this->config->getUserValue($userId, Application::APP_ID, 'board:' . $boardId . ':cardDetailsInModal', $defaultState);
	}

	public function isCardIdBadgeEnabled(): bool {
		$userId = $this->getUserId();
		if ($userId === null) {
			return false;
		}
		$appConfigState = $this->config->getAppValue(Application::APP_ID, 'cardIdBadge', 'yes') === 'no';
		$defaultState = (bool)$this->config->getUserValue($userId, Application::APP_ID, 'cardIdBadge', $appConfigState);

		return (bool)$this->config->getUserValue($userId, Application::APP_ID, 'cardIdBadge', $defaultState);
	}

	public function ensureFederationEnabled() {
		if (!$this->get('federationEnabled')) {
			throw new FederationDisabledException();
		}
		// @TODO fine tune these config values to respect incoming and outgoing federation separately
		if ($this->config->getAppValue('files_sharing', 'outgoing_server2server_share_enabled', 'no') !== 'yes') {
			throw new FederationDisabledException();
		}
		if ($this->config->getAppValue('files_sharing', 'incoming_server2server_share_enabled', 'no') !== 'yes') {
			throw new FederationDisabledException();
		}
	}

	public function set($key, $value) {
		$userId = $this->getUserId();
		if ($userId === null) {
			throw new NoPermissionException('Must be logged in to set user config');
		}

		$result = null;
		[$scope] = explode(':', $key, 2);
		switch ($scope) {
			case 'groupLimit':
				if (!$this->groupManager->isAdmin($userId)) {
					throw new NoPermissionException('You must be admin to set the group limit');
				}
				$result = $this->setGroupLimit($value);
				break;
			case 'federationEnabled':
				if (!$this->groupManager->isAdmin($userId)) {
					throw new NoPermissionException('You must be admin to set the federation enabled setting');
				}
				$this->config->setAppValue(Application::APP_ID, 'federationEnabled', (string)$value);
				$result = $value;
				break;
			case 'calendar':
				$this->config->setUserValue($userId, Application::APP_ID, 'calendar', (string)$value);
				$result = $value;
				break;
			case 'cardDetailsInModal':
				$this->config->setUserValue($userId, Application::APP_ID, 'cardDetailsInModal', (string)$value);
				$result = $value;
				break;
			case 'cardIdBadge':
				$this->config->setUserValue($userId, Application::APP_ID, 'cardIdBadge', (string)$value);
				$result = $value;
				break;
			case 'caldavListMode':
				$allowed = [
					self::SETTING_CALDAV_LIST_MODE_ROOT_TASKS,
					self::SETTING_CALDAV_LIST_MODE_PER_LIST_CALENDAR,
					self::SETTING_CALDAV_LIST_MODE_LIST_AS_CATEGORY,
					self::SETTING_CALDAV_LIST_MODE_LIST_AS_PRIORITY,
				];
				if (!in_array((string)$value, $allowed, true)) {
					throw new BadRequestException('Unsupported CalDAV list mode');
				}
				$this->config->setUserValue($userId, Application::APP_ID, 'caldavListMode', (string)$value);
				$result = (string)$value;
				break;
			case 'board':
				[$boardId, $boardConfigKey] = explode(':', $key);
				if ($boardConfigKey === 'notify-due' && !in_array($value, [self::SETTING_BOARD_NOTIFICATION_DUE_ALL, self::SETTING_BOARD_NOTIFICATION_DUE_ASSIGNED, self::SETTING_BOARD_NOTIFICATION_DUE_OFF], true)) {
					throw new BadRequestException('Board notification option must be one of: off, assigned, all');
				}
				$this->config->setUserValue($userId, Application::APP_ID, $key, (string)$value);
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
