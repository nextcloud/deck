<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Reference;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\BoardService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IL10N;
use OCP\IURLGenerator;

class BoardReferenceProvider implements IReferenceProvider {
	private IURLGenerator $urlGenerator;
	private BoardService $boardService;
	private ?string $userId;
	private IL10N $l10n;

	public function __construct(BoardService $boardService,
		IURLGenerator $urlGenerator,
		IL10N $l10n,
		?string $userId) {
		$this->urlGenerator = $urlGenerator;
		$this->boardService = $boardService;
		$this->userId = $userId;
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example: https://nextcloud.local/index.php/apps/deck/#/board/2
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '(?:\/#!?)?\/board\/[0-9]+$/', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '(?:\/#!?)?\/board\/[0-9]+$/', $referenceText) === 1;

		return $noIndexMatch || $indexMatch;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$boardId = $this->getBoardId($referenceText);
			if ($boardId !== null) {
				try {
					$board = $this->boardService->find($boardId)->jsonSerialize();
				} catch (NoPermissionException $e) {
					// Skip throwing if user has no permissions
					return null;
				}
				$board = $this->sanitizeSerializedBoard($board);
				/** @var IReference $reference */
				$reference = new Reference($referenceText);
				$reference->setTitle($this->l10n->t('Deck board') . ': ' . $board['title']);
				$ownerDisplayName = $board['owner']['displayname'] ?? $board['owner']['uid'] ?? '???';
				$reference->setDescription($this->l10n->t('Owned by %1$s', [$ownerDisplayName]));
				$imageUrl = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'deck-dark.svg')
				);
				$reference->setImageUrl($imageUrl);
				$reference->setRichObject(Application::APP_ID . '-board', [
					'id' => $boardId,
					'board' => $board,
				]);
				return $reference;
			}
		}

		return null;
	}

	private function sanitizeSerializedBoard(array $board): array {
		unset($board['labels']);
		$board['owner'] = $board['owner']->jsonSerialize();
		unset($board['acl']);
		unset($board['users']);

		return $board;
	}

	private function getBoardId(string $url): ?int {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '(?:\/#!?)?\/board\/([0-9]+)$/', $url, $matches);
		if (!$matches) {
			preg_match('/^' . preg_quote($startIndex, '/') . '(?:\/#!?)?\/board\/([0-9]+)$/', $url, $matches);
		}
		if ($matches && count($matches) > 1) {
			return (int)$matches[1];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		$boardId = $this->getBoardId($referenceId);
		if ($boardId !== null) {
			return (string)$boardId;
		}

		return $referenceId;
	}

	public function getCacheKey(string $referenceId): ?string {
		return $this->userId ?? '';
	}
}
