<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Reference;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\Label;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\StackService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IL10N;
use OCP\IURLGenerator;

class CardReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	private CardService $cardService;
	private IURLGenerator $urlGenerator;
	private BoardService $boardService;
	private StackService $stackService;
	private ?string $userId;
	private IL10N $l10n;

	public function __construct(CardService $cardService,
		BoardService $boardService,
		StackService $stackService,
		IURLGenerator $urlGenerator,
		IL10N $l10n,
		?string $userId) {
		$this->cardService = $cardService;
		$this->urlGenerator = $urlGenerator;
		$this->boardService = $boardService;
		$this->stackService = $stackService;
		$this->userId = $userId;
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return Application::APP_ID . '-ref-cards';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Deck boards, cards and comments');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'deck-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		return [
			'search-deck-card-board',
			'search-deck-comment',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example: https://nextcloud.local/index.php/apps/deck/#/board/2/card/11
		$noIndexMatchFull = preg_match('/^' . preg_quote($start, '/') . '(?:\/#!?)?\/board\/[0-9]+\/card\/[0-9]+$/', $referenceText) === 1;
		$indexMatchFull = preg_match('/^' . preg_quote($startIndex, '/') . '(?:\/#!?)?\/board\/[0-9]+\/card\/[0-9]+$/', $referenceText) === 1;

		// link example: https://nextcloud.local/index.php/apps/deck/card/11
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/card\/[0-9]+$/', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/card\/[0-9]+$/', $referenceText) === 1;

		return $noIndexMatchFull || $indexMatchFull || $noIndexMatch || $indexMatch;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$ids = $this->getBoardCardId($referenceText);
			if ($ids !== null) {
				[, $cardId] = $ids;
				try {
					$card = $this->cardService->find((int)$cardId)->jsonSerialize();
					$stack = $this->stackService->find((int)$card['stackId'])->jsonSerialize();
					$board = $this->boardService->find((int)$stack['boardId'])->jsonSerialize();
				} catch (NoPermissionException $e) {
					// Skip throwing if user has no permissions
					return null;
				}

				$boardId = $board['id'];

				$card = $this->sanitizeSerializedCard($card);
				$board = $this->sanitizeSerializedBoard($board);
				$stack = $this->sanitizeSerializedStack($stack);
				/** @var IReference $reference */
				$reference = new Reference($referenceText);
				$reference->setRichObject(Application::APP_ID . '-card', [
					'id' => $boardId . '/' . $cardId,
					'card' => $card,
					'board' => $board,
					'stack' => $stack,
				]);
				return $reference;
			}
		}

		return null;
	}

	private function sanitizeSerializedStack(array $stack): array {
		$stack['cards'] = array_map(function (CardDetails $cardDetails) {
			$result = $cardDetails->jsonSerialize();
			unset($result['assignedUsers']);
			return $result;
		}, $stack['cards'] ?? []);

		return $stack;
	}

	private function sanitizeSerializedBoard(array $board): array {
		unset($board['labels']);
		$board['owner'] = $board['owner']?->jsonSerialize();
		unset($board['acl']);
		unset($board['users']);

		return $board;
	}

	private function sanitizeSerializedCard(array $card): array {
		$card['labels'] = array_map(function (Label $label) {
			return $label->jsonSerialize();
		}, $card['labels'] ?? []);
		$card['assignedUsers'] = array_map(function (Assignment $assignment) {
			$result = $assignment->jsonSerialize();
			$result['participant'] = $result['participant']->jsonSerialize();
			return $result;
		}, $card['assignedUsers'] ?? []);
		$card['owner'] = $card['owner']?->jsonSerialize() ?? $card['owner'];
		unset($card['relatedStack']);
		unset($card['relatedBoard']);
		$card['attachments'] = array_map(function (Attachment $attachment) {
			return $attachment->jsonSerialize();
		}, $card['attachments'] ?? []);

		return $card;
	}

	private function getBoardCardId(string $url): ?array {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '(?:\/#!?)?\/board\/([0-9]+)\/card\/([0-9]+)$/', $url, $matches);
		if ($matches && count($matches) > 2) {
			return [$matches[1], $matches[2]];
		}

		preg_match('/^' . preg_quote($startIndex, '/') . '(?:\/#!?)?\/board\/([0-9]+)\/card\/([0-9]+)$/', $url, $matches2);
		if ($matches2 && count($matches2) > 2) {
			return [$matches2[1], $matches2[2]];
		}

		preg_match('/^' . preg_quote($start, '/') . '\/card\/([0-9]+)$/', $url, $matches);
		if ($matches && count($matches) > 1) {
			return [null, $matches[1]];
		}

		preg_match('/^' . preg_quote($startIndex, '/') . '\/card\/([0-9]+)$/', $url, $matches2);
		if ($matches2 && count($matches2) > 1) {
			return [null, $matches2[1]];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		$ids = $this->getBoardCardId($referenceId);
		if ($ids !== null) {
			[$boardId, $cardId] = $ids;
			return $boardId . '/' . $cardId;
		}

		return $referenceId;
	}

	public function getCacheKey(string $referenceId): ?string {
		return $this->userId ?? '';
	}
}
