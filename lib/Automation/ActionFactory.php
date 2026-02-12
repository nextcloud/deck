<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Automation;

use OCA\Deck\Automation\Actions\AddLabelAction;
use OCA\Deck\Automation\Actions\ArchiveAction;
use OCA\Deck\Automation\Actions\RemoveDoneAction;
use OCA\Deck\Automation\Actions\RemoveLabelAction;
use OCA\Deck\Automation\Actions\SetDoneAction;
use OCA\Deck\Automation\Actions\WebhookAction;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Service\CardService;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class ActionFactory {
	public const ACTION_ADD_LABEL = 'add_label';
	public const ACTION_REMOVE_LABEL = 'remove_label';
	public const ACTION_SET_DONE = 'set_done';
	public const ACTION_REMOVE_DONE = 'remove_done';
	public const ACTION_WEBHOOK = 'webhook';
	public const ACTION_ARCHIVE = 'archive';

	public function __construct(
		private CardMapper $cardMapper,
		private LabelMapper $labelMapper,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	public function createAction(string $actionType): ?ActionInterface {
		return match ($actionType) {
			self::ACTION_ADD_LABEL => new AddLabelAction($this->cardMapper, $this->labelMapper, $this->logger),
			self::ACTION_REMOVE_LABEL => new RemoveLabelAction($this->cardMapper, $this->labelMapper, $this->logger),
			self::ACTION_SET_DONE => new SetDoneAction($this->cardMapper, $this->logger),
			self::ACTION_REMOVE_DONE => new RemoveDoneAction($this->cardMapper, $this->logger),
			self::ACTION_WEBHOOK => new WebhookAction($this->clientService, $this->logger),
			self::ACTION_ARCHIVE => new ArchiveAction($this->cardMapper, $this->logger),
			default => null,
		};
	}

	public function getSupportedActions(): array {
		return [
			self::ACTION_ADD_LABEL,
			self::ACTION_REMOVE_LABEL,
			self::ACTION_SET_DONE,
			self::ACTION_REMOVE_DONE,
			self::ACTION_WEBHOOK,
			self::ACTION_ARCHIVE,
		];
	}
}
