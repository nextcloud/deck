<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getStackId()
 * @method void setStackId(int $stackId)
 * @method string getEvent()
 * @method void setEvent(string $event)
 * @method string getActionType()
 * @method void setActionType(string $actionType)
 * @method string getActionConfig()
 * @method void setActionConfig(string $actionConfig)
 * @method int getOrder()
 * @method void setOrder(int $order)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class StackAutomation extends RelationalEntity {
	protected $stackId;
	protected $event;
	protected $actionType;
	protected $actionConfig;
	protected $order;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('stackId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		$data = parent::jsonSerialize();
		// Decode JSON config for easier frontend consumption
		if (isset($data['actionConfig'])) {
			$data['actionConfig'] = json_decode($data['actionConfig'], true) ?? [];
		}
		return $data;
	}

	public function setActionConfigArray(array $config): void {
		$this->setActionConfig(json_encode($config));
	}

	public function getActionConfigArray(): array {
		$decoded = json_decode($this->actionConfig ?? '{}', true);
		return is_array($decoded) ? $decoded : [];
	}
}
