<?php
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getId()
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getExternalId()
 * @method void setExternalId(string $externalId)
 * @method string getOwner()
 * @method void setOwner(string $owner)
 * @method mixed getParticipant()
 * @method void setParticipant($participant)
 */
class ExternalBoard extends RelationalEntity {
	protected $title;
	protected $externalId;
	protected $owner;
	protected $participant;
	public function __construct() {
		$this->AddType('id', 'integer');
		$this->addType('title', 'string');
		$this->addType('externalId', 'string');
		$this->addType('owner', 'string');
		$this->addResolvable('participant');
	}
}
