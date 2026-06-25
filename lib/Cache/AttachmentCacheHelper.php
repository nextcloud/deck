<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Cache;

use OCP\ICache;
use OCP\ICacheFactory;

class AttachmentCacheHelper {
	/** @var ICache */
	private $cache;

	public function __construct(ICacheFactory $cacheFactory) {
		$this->cache = $cacheFactory->createDistributed('deck-attachments');
	}

	public function getAttachmentCount(int $cardId): ?int {
		return $this->cache->get('count-' . $cardId);
	}

	public function setAttachmentCount(int $cardId, int $count): void {
		$this->cache->set('count-' . $cardId, $count);
	}

	public function clearAttachmentCount(int $cardId): void {
		$this->cache->remove('count-' . $cardId);
	}
}
