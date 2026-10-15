<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Provider;

use OC\Security\SecureRandom;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Text\Context\IContext;
use OCA\Text\Context\SessionInfo;
use OCA\Text\Db\Document;
use OCP\Files\File;

class TextContextProvider implements IContext {
	public const CONTEXT_TYPE = 'deck_card';

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
		return self::CONTEXT_TYPE;
	}

	public function toString(): string {
		return $this->getType() . ' (' . $this->getId() . ')';
	}

	public function buildDocument(): Document{
		$document = new Document();
		$document->setContextType($this->getType());
		$document->setContextId($this->getId());
		$document->setLastSavedVersion(0);
		$document->setLastSavedVersionTime($this->getLastModified());
		$document->setLastSavedVersionEtag($this->secureRandom->generate(6));

		$document->setChecksum($this->computeChecksum());
		$document->setBaseVersionEtag(uniqid());
		return $document;
	}

	public function prepareSession(): SessionInfo {
		$readonly = !$this->permissionService->checkPermission($this->cardMapper, $this->cardId, Acl::PERMISSION_EDIT, $this->userId);
		return new SessionInfo(
			readOnly: $readonly,
			lock: null,
			canAttachFiles: false,
		);
	}

	public function isReadOnly(): bool {
		return !$this->permissionService->checkPermission($this->cardMapper, $this->cardId, Acl::PERMISSION_EDIT, $this->userId);
	}

	public function updateDocument(Document $document): void {
		$document->setChecksum($this->computeChecksum());
		$document->setLastSavedVersionTime($this->getLastModified());
	}

	private function getLastModified(): int {
		$card = $this->cardService->find($this->cardId);
		return (int)($card->getLastModified() ?: time());
	}

	public function getFile(): ?File{
		return null;
	}

	public function loadContent(): ?string {
		$card = $this->cardService->find($this->cardId);
		return $card->getDescription();
	}

	public function cleanup(): void {
	}

	public function save(string $content, callable $afterSave): void {
		$this->cardService->updateDescription($this->cardId, $content);
		$afterSave();
	}

	private function computeChecksum(): string {
		$card = $this->cardService->find($this->cardId);
		return hash('crc32', $card->getDescription());
	}

}
