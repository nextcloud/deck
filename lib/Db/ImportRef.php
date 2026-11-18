<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One object an importing app wrote through the board import API, found
 * again by the app's reference
 *
 * @method string getApp()
 * @method void setApp(string $app)
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method int getBoardId()
 * @method void setBoardId(int $boardId)
 * @method string getRef()
 * @method void setRef(string $ref)
 * @method string getRefHash()
 * @method void setRefHash(string $refHash)
 * @method int getObjectId()
 * @method void setObjectId(int $objectId)
 * @method ?string getSourceHash()
 * @method void setSourceHash(?string $sourceHash)
 * @method ?string getStoredHash()
 * @method void setStoredHash(?string $storedHash)
 * @method int getState()
 * @method void setState(int $state)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ImportRef extends Entity {
	public const KIND_BOARD = 'board';
	public const KIND_STACK = 'stack';
	public const KIND_LABEL = 'label';
	public const KIND_CARD = 'card';
	public const KIND_ACL = 'acl';

	public const STATE_ACTIVE = 0;
	/** The importer moved the object to Deck's deleted items */
	public const STATE_REMOVED = 1;

	protected $app;
	protected $kind;
	protected $boardId = 0;
	protected $ref;
	protected $refHash;
	protected $objectId;
	protected $sourceHash;
	protected $storedHash;
	protected $state = self::STATE_ACTIVE;
	protected $createdAt = 0;
	protected $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('objectId', 'integer');
		$this->addType('state', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public static function hashRef(string $ref): string {
		return hash('sha256', $ref);
	}
}
