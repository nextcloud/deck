<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\CardMapper;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CommentServiceTest extends TestCase {
	private ICommentsManager&MockObject $commentsManager;
	private IEventDispatcher&MockObject $eventDispatcher;
	private CommentService $service;

	public function setUp(): void {
		parent::setUp();

		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->service = new CommentService(
			$this->commentsManager,
			$this->createMock(PermissionService::class),
			$this->createMock(CardMapper::class),
			$this->createMock(IUserManager::class),
			$this->createMock(LoggerInterface::class),
			'admin',
			$this->eventDispatcher,
		);
	}

	private function createComment(): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn('42');
		$comment->method('getObjectType')->willReturn(Application::COMMENT_ENTITY_TYPE);
		$comment->method('getObjectId')->willReturn('7');
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getActorId')->willReturn('admin');
		$comment->method('getMessage')->willReturn('Secret message text');
		$comment->method('getCreationDateTime')->willReturn(new \DateTime());
		$comment->method('getMentions')->willReturn([]);
		$comment->method('getParentId')->willReturn('0');
		return $comment;
	}

	private function expectAuditEvent(string $operation, string $message): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === $operation
				&& $event->getLogMessage() === $message
				&& $event->getParameters() === ['commentId' => '42', 'cardId' => 7]));
	}

	public function testCreateLogsCriticalActionWithoutMessage(): void {
		$this->commentsManager->method('create')->willReturn($this->createComment());
		$this->expectAuditEvent('deck.comment.created', 'Comment %s was added to card %s');

		$this->service->create(7, 'Secret message text');
	}

	public function testUpdateLogsCriticalAction(): void {
		$this->commentsManager->method('get')->willReturn($this->createComment());
		$this->expectAuditEvent('deck.comment.updated', 'Comment %s was edited on card %s');

		$this->service->update(7, 42, 'New text');
	}

	public function testDeleteLogsCriticalAction(): void {
		$this->commentsManager->method('get')->willReturn($this->createComment());
		$this->expectAuditEvent('deck.comment.deleted', 'Comment %s was deleted from card %s');

		$this->service->delete(7, 42);
	}
}
