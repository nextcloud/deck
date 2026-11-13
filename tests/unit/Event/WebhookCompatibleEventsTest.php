<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Deck\Event;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCP\EventDispatcher\IWebhookCompatibleEvent;
use Test\TestCase;

class WebhookCompatibleEventsTest extends TestCase {
	public function testCardEventIsWebhookCompatible(): void {
		$card = new Card();
		$card->setId(42);
		$card->setTitle('Test card');
		$card->setStackId(1);

		$event = new CardCreatedEvent($card);

		$this->assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		$payload = $event->getWebhookSerializable();
		$this->assertArrayHasKey('card', $payload);
		$this->assertSame(42, $payload['card']['id']);
		$this->assertSame('Test card', $payload['card']['title']);
	}

	public function testCardEventSerializesNestedEntitiesAsArrays(): void {
		$label = new Label();
		$label->setId(5);
		$label->setTitle('foo');
		$label->setColor('ff0000');

		$card = new Card();
		$card->setId(42);
		$card->setTitle('Test card');
		$card->setStackId(1);
		$card->setLabels([$label]);

		$payload = (new CardUpdatedEvent($card))->getWebhookSerializable();

		$this->assertIsArray($payload['card']['labels'][0]);
		$this->assertSame(5, $payload['card']['labels'][0]['id']);
		$this->assertSame('foo', $payload['card']['labels'][0]['title']);
	}

	public function testAclEventIsWebhookCompatible(): void {
		$acl = new Acl();
		$acl->setId(7);
		$acl->setBoardId(3);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('alice');
		$acl->setPermissionEdit(true);

		$event = new AclCreatedEvent($acl);

		$this->assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		$payload = $event->getWebhookSerializable();
		$this->assertArrayHasKey('acl', $payload);
		$this->assertSame(7, $payload['acl']['id']);
		$this->assertSame(3, $payload['acl']['boardId']);
		$this->assertArrayNotHasKey('token', $payload['acl'], 'token must be stripped from serialized ACL');
	}

	public function testBoardUpdatedEventIsWebhookCompatible(): void {
		$event = new BoardUpdatedEvent(99);

		$this->assertInstanceOf(IWebhookCompatibleEvent::class, $event);
		$this->assertSame(['boardId' => 99], $event->getWebhookSerializable());
	}
}
