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
use OCA\ShareReview\Sources\ISource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Share\Events\ShareReviewAccessCheckEvent;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements ISource {

	private const PERMISSION_MANAGE = 32;

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
	 * @return list<array{id: int, object: string, initiator: string, type: int, recipient: string, permissions: int, password: bool, time: string, action: string}>
	 */
	public function getShares(): array {
		try {
			$rawShares = $this->aclMapper->findAllForShareReview();
		} catch (Exception $e) {
			$this->logger->error('Deck ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		return array_map(
			fn (array $share) => $this->buildShare($share)->toArray(),
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
	private function buildShare(array $share): ShareReviewShare {
		return new ShareReviewShare(
			id: (int)$share['id'],
			object: $this->resolveObjectName($share),
			initiator: (string)$share['board_owner'],
			type: $this->mapParticipantType((int)$share['type']),
			recipient: (string)$share['participant'],
			permissions: $this->computePermissions($share),
			time: date('Y-m-d H:i:s', max((int)$share['created_at'], (int)$share['last_modified_at'])),
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

	/** @param array<string, mixed> $share */
	private function computePermissions(array $share): int {
		$permissions = Constants::PERMISSION_READ;
		if ($share['permission_edit']) {
			$permissions |= Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE | Constants::PERMISSION_DELETE;
		}
		if ($share['permission_share']) {
			$permissions |= Constants::PERMISSION_SHARE;
		}
		if ($share['permission_manage']) {
			$permissions |= self::PERMISSION_MANAGE;
		}
		return $permissions;
	}
}
