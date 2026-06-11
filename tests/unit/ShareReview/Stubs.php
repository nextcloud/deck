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

	final class ShareReviewPermission {
		public function __construct(
			public readonly string $id,
			public readonly string $displayName,
			public readonly ?string $hint = null,
			public readonly int $priority = 50,
		) {
		}
	}

	final class ShareReviewEntry {
		public function __construct(
			public readonly string $id,
			public readonly string $object,
			public readonly string $initiator,
			public readonly int $type,
			public readonly string $recipient,
			public readonly int $lastModifiedTimestamp,
			public readonly array $permissions = [],
			public readonly string $action = '',
			public readonly bool $hasPassword = false,
			public readonly ?int $expirationTimestamp = null,
			public readonly ?string $parent = null,
		) {
		}
	}
}

namespace OCP\Share\ShareReview\Events {

	class ShareReviewAccessCheckEvent extends \OCP\EventDispatcher\Event {
		private bool $handled = false;
		private bool $granted = false;
		private ?string $reason = null;

		public function __construct(
			private readonly string $sourceName,
			private readonly string $shareId,
		) {
			parent::__construct();
		}

		public function getSourceName(): string {
			return $this->sourceName;
		}

		public function getShareId(): string {
			return $this->shareId;
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
