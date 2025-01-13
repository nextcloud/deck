<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Model\Public;

use JsonSerializable;

/**
 * A data transfer object representation of the card data to be used as a
 * public-facing event payload.
 *
 * @since 2.0.0
 */
final class CardEventData implements JsonSerializable {

	/**
	 * @param string $title
	 * @param string $description
	 * @param int $boardId
	 * @param int $stackId
	 * @param \DateTime $lastModified
	 * @param \DateTime $createdAt
	 * @param array<array{ id: int, title: string }> $labels
	 * @param string[] $assignedUsers
	 * @param int $order
	 * @param bool $archived
	 * @param int $commentsUnread
	 * @param int $commentsCount
	 * @param ?string $owner
	 * @param ?string $lastEditor
	 * @param ?\DateTime $duedate
	 * @param ?\DateTime $doneAt
	 * @param ?\DateTime $deletedAt
	 *
	 * @since 2.0.0
	 */
	public function __construct(
		/** @readonly */
		public string $title,
		/** @readonly */
		public string $description,
		/** @readonly */
		public int $boardId,
		/** @readonly */
		public int $stackId,
		/** @readonly */
		public \DateTime $lastModified,
		/** @readonly */
		public \DateTime $createdAt,
		/** @readonly */
		public array $labels = [],
		/** @readonly */
		public array $assignedUsers = [],
		/** @readonly */
		public int $order = 0,
		/** @readonly */
		public bool $archived = false,
		/** @readonly */
		public int $commentsUnread = 0,
		/** @readonly */
		public int $commentsCount = 0,
		/** @readonly */
		public ?string $owner = null,
		/** @readonly */
		public ?string $lastEditor,
		/** @readonly */
		public ?\DateTime $duedate = null,
		/** @readonly */
		public ?\DateTime $doneAt = null,
		/** @readonly */
		public ?\DateTime $deletedAt = null,
	) {
	}


	/**
	 * Serialize the object to a JSON-compatible array.
	 *
	 * @return array{
	 *     title: string,
	 *     description: string,
	 *     boardId: int,
	 *     stackId: int,
	 *     lastModified: string,
	 *     createdAt: string,
	 *     labels: array<array{id: int, title: string}>,
	 *     assignedUsers: string[],
	 *     order: int,
	 *     archived: bool,
	 *     commentsUnread: int,
	 *     commentsCount: int,
	 *     owner: ?string,
	 *     lastEditor: ?string,
	 *     duedate: ?string,
	 *     doneAt: ?string,
	 *     deletedAt: ?string,
	 * }
	 */
	public function jsonSerialize(): array {
		return [
			'title' => $this->title,
			'description' => $this->description,
			'boardId' => $this->boardId,
			'stackId' => $this->stackId,
			'lastModified' => $this->lastModified->format(DATE_ATOM),
			'createdAt' => $this->createdAt->format(DATE_ATOM),
			'labels' => $this->labels,
			'assignedUsers' => $this->assignedUsers,
			'order' => $this->order,
			'archived' => $this->archived,
			'commentsUnread' => $this->commentsUnread,
			'commentsCount' => $this->commentsCount,
			'owner' => $this->owner,
			'lastEditor' => $this->lastEditor,
			'duedate' => $this->duedate?->format(DATE_ATOM),
			'doneAt' => $this->doneAt?->format(DATE_ATOM),
			'deletedAt' => $this->deletedAt?->format(DATE_ATOM),
		];
	}
}
