<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Deck\Db;

/**
 *
 */
interface IPermissionMapper {

	/**
	 * Check if $userId is owner of Entity with $id
	 *
	 * @param $userId string userId
	 * @param $id int unique entity identifier
	 * @return boolean
	 */
	public function isOwner(string $userId, int $id): bool;

	/**
	 * Query boardId for Entity of given $id
	 *
	 * @param $id int unique entity identifier
	 * @return ?int id of Board
	 */
	public function findBoardId(int $id): ?int;
}
