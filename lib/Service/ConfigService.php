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

use OCA\Deck\AppInfo\Application;
use OCA\Deck\NoPermissionException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;

class ConfigService {
	private $config;
	private $userId;
	private $groupManager;

	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
		$userId
	) {
		$this->userId = $userId;
		$this->groupManager = $groupManager;
		$this->config = $config;
	}

	public function getAll(): array {
		$data = [
			'calendar' => $this->get('calendar')
		];
		if ($this->groupManager->isAdmin($this->userId)) {
			$data = [
				'groupLimit' => $this->get('groupLimit'),
			];
		}
		return $data;
	}

	public function get($key) {
		$result = null;
		switch ($key) {
			case 'groupLimit':
				if (!$this->groupManager->isAdmin($this->userId)) {
					throw new NoPermissionException('You must be admin to get the group limit');
				}
				$result = $this->getGroupLimit();
				break;
			case 'calendar':
				$result = (bool)$this->config->getUserValue($this->userId, Application::APP_ID, 'calendar', true);
				break;
		}
		return $result;
	}

	public function set($key, $value) {
		$result = null;
		switch ($key) {
			case 'groupLimit':
				if (!$this->groupManager->isAdmin($this->userId)) {
					throw new NoPermissionException('You must be admin to set the group limit');
				}
				$result = $this->setGroupLimit($value);
				break;
			case 'calendar':
				$this->config->setUserValue($this->userId, Application::APP_ID, 'calendar', (int)$value);
				$result = $value;
				break;
		}
		return $result;
	}

	private function setGroupLimit($value) {
		$groups = [];
		foreach ($value as $group) {
			$groups[] = $group['id'];
		}
		$data = implode(',', $groups);
		$this->config->setAppValue(Application::APP_ID, 'groupLimit', $data);
		return $groups;
	}

	private function getGroupLimitList() {
		$value = $this->config->getAppValue(Application::APP_ID, 'groupLimit', '');
		$groups = explode(',', $value);
		if ($value === '') {
			return [];
		}
		return $groups;
	}

	private function getGroupLimit() {
		$groups = $this->getGroupLimitList();
		$groups = array_map(function ($groupId) {
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
}
