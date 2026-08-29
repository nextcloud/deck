<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\ShareReview;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Service\BoardService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IPaginatedShareReviewSource;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPage;
use OCP\Share\ShareReview\ShareReviewPermission;
use OCP\Share\ShareReview\ShareReviewQuery;
use Psr\Log\LoggerInterface;

/**
 * Deck's board ACLs as share-review shares, with the paginated query contract
 * evaluated in SQL on the ACL/board join.
 */
class ShareReviewSource implements IPaginatedShareReviewSource {

	public const PERMISSION_READ = 'deck:read';
	public const PERMISSION_EDIT = 'deck:edit';
	public const PERMISSION_SHARE = 'deck:share';
	public const PERMISSION_MANAGE = 'deck:manage';

	/** Native ACL participant type to IShare type — identical values by design. */
	private const PARTICIPANT_TYPES = [
		Acl::PERMISSION_TYPE_USER => IShare::TYPE_USER,
		Acl::PERMISSION_TYPE_GROUP => IShare::TYPE_GROUP,
		Acl::PERMISSION_TYPE_REMOTE => IShare::TYPE_REMOTE,
		Acl::PERMISSION_TYPE_CIRCLE => IShare::TYPE_CIRCLE,
	];

	/** Opaque share-review permission id to the ACL column that grants it. */
	private const PERMISSION_COLUMNS = [
		self::PERMISSION_EDIT => 'permission_edit',
		self::PERMISSION_SHARE => 'permission_share',
		self::PERMISSION_MANAGE => 'permission_manage',
	];

	/** @var array<string, ShareReviewPermission>|null */
	private ?array $permissionCatalog = null;

	public function __construct(
		private readonly AclMapper $aclMapper,
		private readonly LoggerInterface $logger,
		private readonly BoardService $boardService,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IL10N $l,
	) {
	}

	public function getName(): string {
		// Deck is a brand name and is never translated
		return 'Deck';
	}

	public function getDisplayName(): string {
		return 'Deck';
	}

	/**
	 * All shares, read page by page in a stable order.
	 *
	 * @return list<ShareReviewEntry>
	 */
	public function getShares(): array {
		// enumerated on the immutable id order, so concurrent edits (which
		// move last_modified_at) can neither duplicate nor skip rows
		$entries = [];
		try {
			foreach ($this->aclMapper->findAllForShareReview() as $row) {
				$entries[] = $this->buildEntry($row);
			}
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		return $entries;
	}

	public function queryShares(ShareReviewQuery $query): ShareReviewPage {
		try {
			$rows = $this->aclMapper->findPageForShareReview($query, $this->participantTypes($query), $this->permissionColumns($query));
			$counts = $this->aclMapper->countForShareReview($query, $this->participantTypes($query), $this->permissionColumns($query));
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return new ShareReviewPage([], new ShareReviewCounts(0, 0));
		}
		return new ShareReviewPage(array_map($this->buildEntry(...), $rows), $counts);
	}

	public function countShares(ShareReviewQuery $query): ShareReviewCounts {
		try {
			return $this->aclMapper->countForShareReview($query, $this->participantTypes($query), $this->permissionColumns($query));
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to count shares: {message}', ['message' => $e->getMessage()]);
			return new ShareReviewCounts(0, 0);
		}
	}

	public function countSharesByType(ShareReviewQuery $query): array {
		try {
			$nativeCounts = $this->aclMapper->countByTypeForShareReview($query, $this->participantTypes($query), $this->permissionColumns($query));
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to count shares by type: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		$counts = [];
		foreach ($nativeCounts as $participantType => $count) {
			// unknown native types are excluded here as they are from the
			// shareTypes filter, so count and filtered list always agree
			if (!isset(self::PARTICIPANT_TYPES[$participantType])) {
				continue;
			}
			$type = self::PARTICIPANT_TYPES[$participantType];
			$counts[$type] = ($counts[$type] ?? 0) + $count;
		}
		return $counts;
	}

	public function getShare(string $shareId): ?ShareReviewEntry {
		if (!ctype_digit($shareId)) {
			return null;
		}
		try {
			$share = $this->aclMapper->findForShareReview((int)$shareId);
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to fetch share {id}: {message}', ['id' => $shareId, 'message' => $e->getMessage()]);
			return null;
		}
		return $share === null ? null : $this->buildEntry($share);
	}

	/**
	 * The native participant types a shareTypes filter selects. Deck's ACL
	 * types carry the same values as their IShare counterparts; a requested
	 * type deck never produces matches nothing.
	 *
	 * @return list<int>|null null = no type filter
	 */
	private function participantTypes(ShareReviewQuery $query): ?array {
		if ($query->shareTypes === null) {
			return null;
		}
		return array_values(array_intersect($query->shareTypes, array_keys(self::PARTICIPANT_TYPES)));
	}

	/**
	 * The permission columns an opaque permission-id filter selects. Read is
	 * granted by every ACL, so asking for it disables the filter; ids of other
	 * apps match nothing.
	 *
	 * @return list<string>|null null = no permission filter
	 */
	private function permissionColumns(ShareReviewQuery $query): ?array {
		if ($query->permissionIds === null || in_array(self::PERMISSION_READ, $query->permissionIds, true)) {
			return null;
		}
		return array_values(array_intersect_key(self::PERMISSION_COLUMNS, array_fill_keys($query->permissionIds, true)));
	}

	public function deleteShare(string $shareId): bool {
		if (!ctype_digit($shareId)) {
			return false;
		}

		$event = new ShareReviewAccessCheckEvent('Deck', $shareId);
		$this->eventDispatcher->dispatchTyped($event);

		if (!$event->isHandled() || !$event->isGranted()) {
			return false;
		}

		try {
			$this->boardService->deleteAclForShareReview((int)$shareId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		}
	}

	/** @param array<string, mixed> $share */
	private function buildEntry(array $share): ShareReviewEntry {
		return new ShareReviewEntry(
			id: (string)$share['id'],
			object: $this->resolveObjectName($share),
			initiator: (string)$share['board_owner'],
			type: $this->mapParticipantType((int)$share['type']),
			recipient: (string)$share['participant'],
			// The mapper bumps last_modified_at on every insert and update, so
			// it is the share time; rows predating the columns keep 0 and fall
			// back to created_at (also 0 for them, by design — no backfill).
			lastModifiedTimestamp: (int)$share['last_modified_at'] ?: (int)$share['created_at'],
			permissions: $this->buildPermissions($share),
		);
	}

	/** @param array<string, mixed> $share */
	private function resolveObjectName(array $share): string {
		$title = (string)($share['board_title'] ?? '');
		$boardId = (int)($share['board_id'] ?? $share['id']);
		$label = $title !== '' ? $title : $this->l->t('Board %d', [$boardId]);
		return $this->l->t('%s (Board)', [$label]);
	}

	private function mapParticipantType(int $type): int {
		if (isset(self::PARTICIPANT_TYPES[$type])) {
			return self::PARTICIPANT_TYPES[$type];
		}
		$this->logger->warning('Deck ShareReview: unknown ACL participant type {type}, defaulting to user share', ['type' => $type]);
		return IShare::TYPE_USER;
	}

	/**
	 * @param array<string, mixed> $share
	 * @return list<ShareReviewPermission>
	 */
	private function buildPermissions(array $share): array {
		$catalog = $this->permissionCatalog();
		$permissions = [$catalog[self::PERMISSION_READ]];
		if ($share['permission_edit']) {
			$permissions[] = $catalog[self::PERMISSION_EDIT];
		}
		if ($share['permission_share']) {
			$permissions[] = $catalog[self::PERMISSION_SHARE];
		}
		if ($share['permission_manage']) {
			$permissions[] = $catalog[self::PERMISSION_MANAGE];
		}
		return $permissions;
	}

	/**
	 * The permission objects are immutable and identical for every share row,
	 * so they are built once per request instead of once per row.
	 *
	 * All permission IDs are namespaced to this app, and labels and hints are
	 * translated from this app's own catalog — the app owning a permission
	 * also owns its wording in every language.
	 *
	 * @return array<string, ShareReviewPermission>
	 */
	private function permissionCatalog(): array {
		return $this->permissionCatalog ??= [
			self::PERMISSION_READ => new ShareReviewPermission(self::PERMISSION_READ, $this->l->t('Read'), priority: 80),
			self::PERMISSION_EDIT => new ShareReviewPermission(self::PERMISSION_EDIT, $this->l->t('Edit'), $this->l->t('Create, update and delete cards'), 70),
			self::PERMISSION_SHARE => new ShareReviewPermission(self::PERMISSION_SHARE, $this->l->t('Re-share'), priority: 40),
			self::PERMISSION_MANAGE => new ShareReviewPermission(self::PERMISSION_MANAGE, $this->l->t('Manage board'), $this->l->t('Administer participants and board settings'), 30),
		];
	}
}
