<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
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
		return $this->exportAttachmentsForCards([$cardId], $fallbackUserId)[$cardId] ?? [];
	}

	/**
	 * Export the file shares of many cards at once, keyed by card id, so that
	 * exporting a whole board does not run one query per card.
	 *
	 * @param int[] $cardIds
	 * @return array<int, list<array<string, int|string>>>
	 */
	public function exportAttachmentsForCards(array $cardIds, string $fallbackUserId): array {
		$formattedAttachments = [];
		foreach ($this->getShareFileAttachments($cardIds) as $share) {
			$shareAttachment = $this->serializeShareAttachment($share, $fallbackUserId);
			if ($shareAttachment !== null) {
				$formattedAttachments[(int)$share['share_with']][] = $shareAttachment;
			}
		}

		return $formattedAttachments;
	}

	/**
	 * Count the file shares of many cards without reading any file contents,
	 * so an export that leaves attachments out can still report how many a
	 * card has.
	 *
	 * @param int[] $cardIds
	 * @return array<int, int>
	 */
	public function countAttachmentsForCards(array $cardIds): array {
		if (empty($cardIds)) {
			return [];
		}

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('share_with')
			->selectAlias($qb->func()->count('id'), 'attachment_count')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(12)))
			->andWhere($qb->expr()->in('share_with', $qb->createNamedParameter(
				array_map('strval', $cardIds),
				IQueryBuilder::PARAM_STR_ARRAY,
			)))
			->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter('file')))
			->groupBy('share_with');

		$counts = [];
		foreach ($qb->executeQuery()->fetchAllAssociative() as $row) {
			$counts[(int)$row['share_with']] = (int)$row['attachment_count'];
		}

		return $counts;
	}

	/**
	 * @param int[] $cardIds
	 * @return array<int, array<string, mixed>>
	 */
	private function getShareFileAttachments(array $cardIds): array {
		if (empty($cardIds)) {
			return [];
		}

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id', 'uid_owner', 'uid_initiator', 'file_source', 'stime', 'share_with')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(12)))
			->andWhere($qb->expr()->in('share_with', $qb->createNamedParameter(
				array_map('strval', $cardIds),
				IQueryBuilder::PARAM_STR_ARRAY,
			)))
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
				'type' => 'file',
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
