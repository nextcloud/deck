<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Provider;

use OC\Security\SecureRandom;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCA\Text\Context\IContext;
use OCA\Text\Context\IContextFactory;
use OCP\IUser;
use OCP\Share\IShare;

class TextContextProviderFactory implements IContextFactory{
	public function __construct(
		private readonly SecureRandom $secureRandom,
		private readonly CardService $cardService,
		private readonly CardMapper $cardMapper,
		private readonly PermissionService $permissionService,
		private readonly string $userId,
	) {
	}

	public function build(IUser|IShare $auth, string $type, int $id): IContext {
		return new TextContextProvider(
			secureRandom: $this->secureRandom,
			cardService: $this->cardService,
			cardMapper: $this->cardMapper,
			permissionService: $this->permissionService,
			userId: $this->userId,
			cardId: $id,
		);
	}
}
