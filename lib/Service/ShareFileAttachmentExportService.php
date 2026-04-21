<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCP\Files\IRootFolder;
use OCP\IDBConnection;

class ShareFileAttachmentExportService {
	public function __construct(
		private IDBConnection $dbConnection,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @return array<int, array<string, int|string>>
	 */
	public function exportCardAttachments(int $cardId, string $fallbackUserId): array {
		$formattedAttachments = [];
		foreach ($this->getShareFileAttachments($cardId) as $share) {
			$shareAttachment = $this->serializeShareAttachment($share, $fallbackUserId);
			if ($shareAttachment !== null) {
				$formattedAttachments[] = $shareAttachment;
			}
		}

		return $formattedAttachments;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getShareFileAttachments(int $cardId): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id', 'uid_owner', 'uid_initiator', 'file_source', 'stime')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(12)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter((string)$cardId)))
			->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter('file')));
		return $qb->executeQuery()->fetchAllAssociative();
	}

	/**
	 * @param array<string, mixed> $share
	 *
	 * @return array<string, int|string>|null
	 */
	private function serializeShareAttachment(array $share, string $fallbackUserId): ?array {
		try {
			$nodes = $this->rootFolder->getById((int)$share['file_source']);
			$node = $nodes[0] ?? null;
			if ($node === null || !method_exists($node, 'getContent') || !method_exists($node, 'getName')) {
				return null;
			}

			return [
				'type' => 'deck_file',
				'data' => (string)$node->getName(),
				'createdBy' => (string)($share['uid_initiator'] ?? $share['uid_owner'] ?? $fallbackUserId),
				'createdAt' => (int)($share['stime'] ?? time()),
				'lastModified' => method_exists($node, 'getMTime') ? (int)$node->getMTime() : (int)($share['stime'] ?? time()),
				'contentBase64' => base64_encode($node->getContent()),
			];
		} catch (\Throwable $e) {
			return null;
		}
	}
}
