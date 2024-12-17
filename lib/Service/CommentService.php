<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException as CommentNotFoundException;
use OCP\IUserManager;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;

use function is_numeric;

class CommentService {

	public function __construct(
		private ICommentsManager $commentsManager,
		private PermissionService $permissionService,
		private CardMapper $cardMapper,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
	}

	public function list(string $cardId, int $limit = 20, int $offset = 0): DataResponse {
		if (!is_numeric($cardId)) {
			throw new BadRequestException('A valid card id must be provided');
		}
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		$comments = $this->commentsManager->getForObject(Application::COMMENT_ENTITY_TYPE, $cardId, $limit, $offset);
		$result = [];
		foreach ($comments as $comment) {
			$formattedComment = $this->formatComment($comment);
			try {
				if ($comment->getParentId() !== '0' && $replyTo = $this->commentsManager->get($comment->getParentId())) {
					$formattedComment['replyTo'] = $this->formatComment($replyTo);
				}
			} catch (CommentNotFoundException $e) {
			}
			$result[] = $formattedComment;
		}
		return new DataResponse($result);
	}

	/**
	 * @param int $cardId
	 * @param int $commentId
	 * @return IComment
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	private function get(int $cardId, int $commentId): IComment {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
		try {
			$comment = $this->commentsManager->get((string)$commentId);
			if ($comment->getObjectType() !== Application::COMMENT_ENTITY_TYPE || (int)$comment->getObjectId() !== $cardId) {
				throw new CommentNotFoundException();
			}
		} catch (CommentNotFoundException $e) {
			throw new NotFoundException('No comment found.');
		}
		if ($comment->getParentId() !== '0') {
			$this->permissionService->checkPermission($this->cardMapper, (int)$comment->getParentId(), Acl::PERMISSION_READ);
		}

		return $comment;
	}

	/**
	 * @param int $cardId
	 * @param int $commentId
	 * @return array
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function getFormatted(int $cardId, int $commentId): array {
		$comment = $this->get($cardId, $commentId);
		return $this->formatComment($comment);
	}

	/**
	 * @throws BadRequestException
	 * @throws NotFoundException|NoPermissionException
	 */
	public function create(int $cardId, string $message, string $replyTo = '0'): DataResponse {
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		// Check if parent is a comment on the same card
		if ($replyTo !== '0') {
			try {
				$comment = $this->commentsManager->get($replyTo);
				if ($comment->getObjectType() !== Application::COMMENT_ENTITY_TYPE || (int)$comment->getObjectId() !== $cardId) {
					throw new CommentNotFoundException();
				}
			} catch (CommentNotFoundException $e) {
				throw new BadRequestException('Invalid parent id: The parent comment was not found or belongs to a different card');
			}
		}

		try {
			$comment = $this->commentsManager->create('users', $this->userId, Application::COMMENT_ENTITY_TYPE, (string)$cardId);
			$comment->setMessage($message);
			$comment->setVerb('comment');
			$comment->setParentId($replyTo);
			$this->commentsManager->save($comment);
			return new DataResponse($this->formatComment($comment, true));
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestException('Invalid input values');
		} catch (MessageTooLongException $e) {
			$msg = 'Message exceeds allowed character limit of ';
			throw new BadRequestException($msg . IComment::MAX_MESSAGE_LENGTH);
		} catch (CommentNotFoundException $e) {
			throw new NotFoundException('Could not create comment.');
		}
	}

	public function update(string $cardId, string $commentId, string $message): DataResponse {
		if (!is_numeric($cardId)) {
			throw new BadRequestException('A valid card id must be provided');
		}
		if (!is_numeric($commentId)) {
			throw new BadRequestException('A valid comment id must be provided');
		}
		$comment = $this->get((int)$cardId, (int)$commentId);
		if ($comment->getActorType() !== 'users' || $comment->getActorId() !== $this->userId) {
			throw new NoPermissionException('Only authors are allowed to edit their comment.');
		}

		$comment->setMessage($message);
		$this->commentsManager->save($comment);
		return new DataResponse($this->formatComment($comment));
	}

	public function delete(string $cardId, string $commentId): DataResponse {
		if (!is_numeric($cardId)) {
			throw new BadRequestException('A valid card id must be provided');
		}
		if (!is_numeric($commentId)) {
			throw new BadRequestException('A valid comment id must be provided');
		}
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		try {
			$comment = $this->commentsManager->get($commentId);
			if ($comment->getObjectType() !== Application::COMMENT_ENTITY_TYPE || $comment->getObjectId() !== $cardId) {
				throw new CommentNotFoundException();
			}
		} catch (CommentNotFoundException $e) {
			throw new NotFoundException('No comment found.');
		}
		if ($comment->getActorType() !== 'users' || $comment->getActorId() !== $this->userId) {
			throw new NoPermissionException('Only authors are allowed to edit their comment.');
		}
		$this->commentsManager->delete($commentId);
		return new DataResponse([]);
	}

	private function formatComment(IComment $comment, $addReplyTo = false): array {
		$actorDisplayName = $this->userManager->getDisplayName($comment->getActorId()) ?? $comment->getActorId();

		$formattedComment = [
			'id' => (int)$comment->getId(),
			'objectId' => (int)$comment->getObjectId(),
			'message' => $comment->getMessage(),
			'actorId' => $comment->getActorId(),
			'actorType' => $comment->getActorType(),
			'actorDisplayName' => $actorDisplayName,
			'creationDateTime' => $comment->getCreationDateTime()->format(\DateTime::ATOM),
			'mentions' => array_map(function ($mention) {
				try {
					$displayName = $this->commentsManager->resolveDisplayName($mention['type'], $mention['id']);
				} catch (OutOfBoundsException $e) {
					$this->logger->warning('Mention type not registered, can not resolve display name.', ['exception' => $e, 'mention_type' => $mention['type']]);
					// No display name, upon client's discretion what to display.
					$displayName = '';
				}

				return [
					'mentionId' => $mention['id'],
					'mentionType' => $mention['type'],
					'mentionDisplayName' => $displayName
				];
			}, $comment->getMentions()),
		];

		try {
			if ($addReplyTo && $comment->getParentId() !== '0' && $replyTo = $this->commentsManager->get($comment->getParentId())) {
				$formattedComment['replyTo'] = $this->formatComment($replyTo);
			}
		} catch (CommentNotFoundException $e) {
		}
		return $formattedComment;
	}
}
