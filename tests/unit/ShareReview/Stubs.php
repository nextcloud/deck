<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Share\ShareReview {

	/**
	 * Runtime stub for servers that do not ship the ShareReview OCP classes yet.
	 * Only loaded when the real classes are not available.
	 */
	interface IShareReviewSource {
		public function getName(): string;

		public function getShares(): array;

		public function deleteShare(string $shareId): bool;
	}

	interface IPaginatedShareReviewSource extends IShareReviewSource {
		public function getDisplayName(): string;

		public function queryShares(ShareReviewQuery $query): ShareReviewPage;

		public function countShares(ShareReviewQuery $query): ShareReviewCounts;

		public function countSharesByType(ShareReviewQuery $query): array;

		public function getShare(string $shareId): ?ShareReviewEntry;
	}

	class RegisterShareReviewSourceEvent extends \OCP\EventDispatcher\Event {
		/** @var array<int, class-string<IShareReviewSource>> */
		private array $sources = [];

		public function registerSource(string $source): void {
			$this->sources[] = $source;
		}

		public function getSources(): array {
			return $this->sources;
		}
	}

	final readonly class ShareReviewPermission {
		public function __construct(
			public string $id,
			public string $displayName,
			public ?string $hint = null,
			public int $priority = 50,
		) {
		}
	}

	final readonly class ShareReviewEntry {
		public function __construct(
			public string $id,
			public string $object,
			public string $initiator,
			public int $type,
			public string $recipient,
			public int $lastModifiedTimestamp,
			public array $permissions = [],
			public string $action = '',
			public bool $hasPassword = false,
			public ?int $expirationTimestamp = null,
			public ?string $parent = null,
		) {
		}
	}

	final class ShareReviewQuery {
		public const SORT_TIME = 'time';
		public const SORT_OBJECT = 'object';
		public const SORT_INITIATOR = 'initiator';
		public const SORT_RECIPIENT = 'recipient';
		public const SORT_TYPE = 'type';
		public const SORTABLE_FIELDS = [self::SORT_TIME, self::SORT_OBJECT, self::SORT_INITIATOR, self::SORT_RECIPIENT, self::SORT_TYPE];
		public const MAX_LIMIT = 500;

		public function __construct(
			public readonly int $limit = 100,
			public readonly int $offset = 0,
			public readonly ?string $search = null,
			public readonly string $sortField = self::SORT_TIME,
			public readonly bool $sortDescending = true,
			public readonly ?int $modifiedSinceTimestamp = null,
			public readonly ?array $shareTypes = null,
			public readonly ?bool $hasPassword = null,
			public readonly ?bool $hasExpiration = null,
			public readonly ?int $expiresAfterTimestamp = null,
			public readonly ?int $expiresBeforeTimestamp = null,
			public readonly ?string $initiatorSearch = null,
			public readonly ?string $recipientSearch = null,
			public readonly ?string $objectSearch = null,
			public readonly ?array $objectSearchAny = null,
			public readonly ?array $initiatorIds = null,
			public readonly ?array $recipientIds = null,
			public readonly ?array $permissionIds = null,
			public readonly ?array $tokens = null,
		) {
			if ($limit < 1 || $limit > self::MAX_LIMIT) {
				throw new \InvalidArgumentException('limit must be between 1 and ' . self::MAX_LIMIT);
			}
			if ($offset < 0) {
				throw new \InvalidArgumentException('offset must not be negative');
			}
			if (!in_array($sortField, self::SORTABLE_FIELDS, true)) {
				throw new \InvalidArgumentException('Unknown sort field');
			}
		}

		public function isFiltered(): bool {
			return $this->search !== null
				|| $this->modifiedSinceTimestamp !== null
				|| $this->shareTypes !== null
				|| $this->hasPassword !== null
				|| $this->hasExpiration !== null
				|| $this->expiresAfterTimestamp !== null
				|| $this->expiresBeforeTimestamp !== null
				|| $this->initiatorSearch !== null
				|| $this->recipientSearch !== null
				|| $this->objectSearch !== null
				|| $this->objectSearchAny !== null
				|| $this->initiatorIds !== null
				|| $this->recipientIds !== null
				|| $this->permissionIds !== null
				|| $this->tokens !== null;
		}
	}

	final readonly class ShareReviewCounts {
		public function __construct(
			public int $totalCount,
			public int $filteredCount,
		) {
		}
	}

	final readonly class ShareReviewPage {
		public function __construct(
			public array $entries,
			public ShareReviewCounts $counts,
		) {
		}
	}
}

namespace OCP\Share\ShareReview\Events {

	class ShareReviewAccessCheckEvent extends \OCP\EventDispatcher\Event {
		public const ACTION_DELETE = 'delete';
		public const ACTION_REMEDIATE = 'remediate';
		public const ACTION_RESTORE = 'restore';
		public const SCOPE_OPERATOR = 'operator';
		public const SCOPE_SELF = 'self';

		private bool $handled = false;
		private bool $granted = false;
		private ?string $reason = null;

		public function __construct(
			private readonly string $sourceName,
			private readonly string $shareId,
			private readonly string $action = self::ACTION_DELETE,
			private readonly ?string $actingUserId = null,
			private readonly string $scope = self::SCOPE_OPERATOR,
		) {
			parent::__construct();
		}

		public function getSourceName(): string {
			return $this->sourceName;
		}

		public function getShareId(): string {
			return $this->shareId;
		}

		public function getAction(): string {
			return $this->action;
		}

		public function getActingUserId(): ?string {
			return $this->actingUserId;
		}

		public function getScope(): string {
			return $this->scope;
		}

		public function grantAccess(): void {
			if ($this->handled && !$this->granted) {
				return;
			}
			$this->handled = true;
			$this->granted = true;
		}

		public function denyAccess(string $reason): void {
			$this->handled = true;
			$this->granted = false;
			$this->reason = $reason;
			$this->stopPropagation();
		}

		public function isHandled(): bool {
			return $this->handled;
		}

		public function isGranted(): bool {
			return $this->granted;
		}

		public function getReason(): ?string {
			return $this->reason;
		}
	}
}
