<?php
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
use OCP\ILogger;
use OCP\IUserManager;
use OutOfBoundsException;
use function is_numeric;

class CommentService {
	private ICommentsManager $commentsManager;
	private IUserManager $userManager;
	private CardMapper $cardMapper;
	private PermissionService $permissionService;
	private ILogger $logger;
	private ?string $userId;

	public function __construct(ICommentsManager $commentsManager, PermissionService $permissionService, CardMapper $cardMapper, IUserManager $userManager, ILogger $logger, ?string $userId) {
		$this->commentsManager = $commentsManager;
		$this->permissionService = $permissionService;
		$this->cardMapper = $cardMapper;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->userId = $userId;
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
	 * @param string $cardId
	 * @param string $message
	 * @param string $replyTo
	 * @return DataResponse
	 * @throws BadRequestException
	 * @throws NotFoundException|NoPermissionException
	 */
	public function create(string $cardId, string $message, string $replyTo = '0'): DataResponse {
		if (!is_numeric($cardId)) {
			throw new BadRequestException('A valid card id must be provided');
		}
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		// Check if parent is a comment on the same card
		if ($replyTo !== '0') {
			try {
				$comment = $this->commentsManager->get($replyTo);
				if ($comment->getObjectType() !== Application::COMMENT_ENTITY_TYPE || $comment->getObjectId() !== $cardId) {
					throw new CommentNotFoundException();
				}
			} catch (CommentNotFoundException $e) {
				throw new BadRequestException('Invalid parent id: The parent comment was not found or belongs to a different card');
			}
		}

		try {
			$comment = $this->commentsManager->create('users', $this->userId, Application::COMMENT_ENTITY_TYPE, $cardId);
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
		if ($comment->getParentId() !== '0') {
			$this->permissionService->checkPermission($this->cardMapper, $comment->getParentId(), Acl::PERMISSION_READ);
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
		$user = $this->userManager->get($comment->getActorId());
		$actorDisplayName = $user !== null ? $user->getDisplayName() : $comment->getActorId();

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
					$this->logger->logException($e);
					// No displayname, upon client's discretion what to display.
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
