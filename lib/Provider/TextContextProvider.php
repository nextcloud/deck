<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Provider;

use Co\Http\Server;
use OC\Security\SecureRandom;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Text\Context\DocumentData;
use OCA\Text\Context\IContext;
use OCA\Text\Context\SessionInfo;
use OCA\Text\Db\Document;
use OCA\Text\Exception\DocumentSaveConflictException;
use OCP\Files\File;

class TextContextProvider implements IContext {
	public function __construct(
		private readonly SecureRandom $secureRandom,
		private readonly CardService $cardService,
		private readonly CardMapper $cardMapper,
		private readonly PermissionService $permissionService,
		private readonly string $userId,
		private readonly int $cardId,
	) {
	}
	public function getId(): int {
		return $this->cardId;
	}

	public function getType(): string {
		return 'deck_card';
	}

	public function toString(): string {
		return $this->getType() . ' (' . $this->getId() . ')';
	}

	public function buildDocument(): Document{
		$document = new Document();
		$document->setContextType($this->getType());
		$document->setContextId($this->getId());
		$document->setLastSavedVersion(0);
		$document->setLastSavedVersionTime(1);
		$document->setLastSavedVersionEtag($this->secureRandom->generate(6));

		$document->setChecksum($this->computeChecksum());
		$document->setBaseVersionEtag(uniqid());
		return $document;
	}

	public function prepareSession(DocumentData $documentData): SessionInfo {
		if ($documentData->documentState === null) {
			$content = null;
		} else {
			$content = $this->cardService->find($this->cardId)->getDescription();
		}
		$readonly = !$this->permissionService->checkPermission($this->cardMapper, $this->cardId, Acl::PERMISSION_EDIT, $this->userId);
		return new SessionInfo(
			content: $content,
			readOnly: $readonly,
			lock: null,
			hasOwner: true,
		);
	}

	public function isReadOnly(): bool {
		return !$this->permissionService->checkPermission($this->cardMapper, $this->cardId, Acl::PERMISSION_EDIT, $this->userId);
	}

	public function updateDocument(Document $document): ?Document {
		if ($this->computeChecksum() !== $document->getChecksum()) {
			$card = $this->cardService->find($this->cardId);
			throw new DocumentSaveConflictException($card->getDescription());
		}
		return null;
	}

	public function getFile(): ?File{
		return null;
	}

	public function loadContent(): ?string {
		$card = $this->cardService->find($this->cardId);
		if ($card === null) {
			return null;
		}
		return $card->getDescription();
	}

	public function saveWithLock(string $content, callable $doWhileLocked): void {
		$card = $this->cardService->find($this->cardId);
		if ($card === null) {
			return;
		}
		$card->setDescription($content);
		$this->cardService->update($card->getId(), $card->getTitle(), $card->getStackId(), $card->getType(), $card->getOwner(), $card->getDescription(), $card->getOrder());
	}

	public function cleanup(): void {
	}

	private function computeChecksum(): string {
		$card = $this->cardService->find($this->cardId);
		if ($card === null) {
			return '';
		}
		return hash('crc32', $card->getDescription());
	}

}
