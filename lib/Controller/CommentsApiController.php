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

namespace OCA\Deck\Controller;


use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;

class CommentsApiController extends ApiController {

	/** @var ICommentsManager */
	private $commentsManager;
	/** @var IUserManager */
	private $userManager;
	/** @var ILogger */
	private $logger;

	public function __construct(
		$appName, IRequest $request, $corsMethods = 'PUT, POST, GET, DELETE, PATCH', $corsAllowedHeaders = 'Authorization, Content-Type, Accept', $corsMaxAge = 1728000,
		ICommentsManager $commentsManager,
		IUserManager $userManager,
		ILogger $logger
	) {
		parent::__construct($appName, $request, $corsMethods, $corsAllowedHeaders, $corsMaxAge);

		$this->commentsManager = $commentsManager;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function list(int $cardId, $limit = 20, $offset = 0): JSONResponse {
		$comments = $this->commentsManager->getForObject('deckCard', $cardId, $limit, $offset);
		$result = [];
		foreach ($comments as $comment) {
			$formattedComment = $this->formatComment($comment);
			try {
				if ($comment->getParentId() !== '0' && $replyTo = $this->commentsManager->get($comment->getParentId())) {
					$formattedComment['replyTo'] = $this->formatComment($replyTo);
				}
			} catch (NotFoundException $e) {
			}
			$result[] = $formattedComment;
		}
		return new JSONResponse($result);
	}

	private function formatComment(IComment $comment): array {
		$user = $this->userManager->get($comment->getActorId());
		$actorDisplayName = $user !== null ? $user->getDisplayName() : $comment->getActorId();

		return [
			'id' => $comment->getId(),
			'message' => $comment->getMessage(),
			'actorId' => $comment->getActorId(),
			'actorType' => $comment->getActorType(),
			'actorDisplayName' => $actorDisplayName,
			'mentions' => array_map(function($mention) {
				try {
					$displayName = $this->commentsManager->resolveDisplayName($mention['type'], $mention['id']);
				} catch (\OutOfBoundsException $e) {
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
	}


}
