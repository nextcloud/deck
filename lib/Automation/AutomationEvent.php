<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Automation;

/**
 * Simple event class for automation actions
 * Note: This is different from OCA\Deck\Event\* classes which are Nextcloud event dispatcher events
 */
class AutomationEvent {
public const EVENT_CREATE = 'create';
public const EVENT_DELETE = 'delete';
public const EVENT_ENTER = 'enter';
public const EVENT_EXIT = 'exit';

private string $eventName;
private ?int $fromStackId;
private ?int $toStackId;

public function __construct(
string $eventName,
?int $fromStackId = null,
?int $toStackId = null
) {
$this->eventName = $eventName;
$this->fromStackId = $fromStackId;
$this->toStackId = $toStackId;
}

public function getEventName(): string {
return $this->eventName;
}

public function getFromStackId(): ?int {
return $this->fromStackId;
}

public function getToStackId(): ?int {
return $this->toStackId;
}
}
