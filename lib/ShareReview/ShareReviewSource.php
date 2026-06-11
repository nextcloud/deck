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
use OCP\Share\ShareReview\IShareReviewSource;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements IShareReviewSource {

	public const PERMISSION_READ = 'deck:read';
	public const PERMISSION_EDIT = 'deck:edit';
	public const PERMISSION_SHARE = 'deck:share';
	public const PERMISSION_MANAGE = 'deck:manage';

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
		return 'Deck';
	}

	/**
	 * @return list<ShareReviewEntry>
	 */
	public function getShares(): array {
		try {
			$rawShares = $this->aclMapper->findAllForShareReview();
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		return array_map(
			fn (array $share) => $this->buildEntry($share),
			$rawShares,
		);
	}

	public function deleteShare(string $shareId): bool {
		if (!is_numeric($shareId)) {
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
			lastModifiedTimestamp: max((int)$share['created_at'], (int)$share['last_modified_at']),
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
		return match($type) {
			Acl::PERMISSION_TYPE_USER => IShare::TYPE_USER,
			Acl::PERMISSION_TYPE_GROUP => IShare::TYPE_GROUP,
			Acl::PERMISSION_TYPE_REMOTE => IShare::TYPE_REMOTE,
			Acl::PERMISSION_TYPE_CIRCLE => IShare::TYPE_CIRCLE,
			default => $this->fallbackParticipantType($type),
		};
	}

	private function fallbackParticipantType(int $type): int {
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
