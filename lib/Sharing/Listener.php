<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Deck\Sharing;

use OC\Files\Filesystem;
use OCA\Deck\Service\ConfigService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Server;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;

class Listener {
	private ConfigService $configService;

	public function __construct(ConfigService $configService) {
		$this->configService = $configService;
	}

	public function register(IEventDispatcher $dispatcher): void {
		/**
		 * @psalm-suppress UndefinedClass
		 */
		$dispatcher->addListener(BeforeShareCreatedEvent::class, [self::class, 'listenPreShare'], 1000);
		$dispatcher->addListener(VerifyMountPointEvent::class, [self::class, 'listenVerifyMountPointEvent'], 1000);
	}

	public static function listenPreShare(BeforeShareCreatedEvent $event): void {
		/** @var self $listener */
		$listener = Server::get(self::class);
		$listener->overwriteShareTarget($event);
	}

	public static function listenVerifyMountPointEvent(VerifyMountPointEvent $event): void {
		/** @var self $listener */
		$listener = Server::get(self::class);
		$listener->overwriteMountPoint($event);
	}

	public function overwriteShareTarget(BeforeShareCreatedEvent $event): void {
		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_DECK
			&& $share->getShareType() !== DeckShareProvider::SHARE_TYPE_DECK_USER) {
			return;
		}

		$target = DeckShareProvider::DECK_FOLDER_PLACEHOLDER . '/' . $share->getNode()->getName();
		$target = Filesystem::normalizePath($target);
		$share->setTarget($target);
	}

	public function overwriteMountPoint(VerifyMountPointEvent $event): void {
		$share = $event->getShare();
		$view = $event->getView();

		if ($share->getShareType() !== IShare::TYPE_DECK
			&& $share->getShareType() !== DeckShareProvider::SHARE_TYPE_DECK_USER) {
			return;
		}

		if ($event->getParent() === DeckShareProvider::DECK_FOLDER_PLACEHOLDER) {
			try {
				$userId = $view->getOwner('/');
			} catch (\Exception $e) {
				// If we fail to get the owner of the view from the cache,
				// e.g. because the user never logged in but a cron job runs
				// We fallback to calculating the owner from the root of the view:
				if (substr_count($view->getRoot(), '/') >= 2) {
					// /37c09aa0-1b92-4cf6-8c66-86d8cac8c1d0/files
					[, $userId, ] = explode('/', $view->getRoot(), 3);
				} else {
					// Something weird is going on, we can't fallback more
					// so for now we don't overwrite the share path ¯\_(ツ)_/¯
					return;
				}
			}

			$parent = $this->configService->getAttachmentFolder($userId);
			$event->setParent($parent);
			if (!$event->getView()->is_dir($parent)) {
				$event->getView()->mkdir($parent);
			}
		}
	}
}
