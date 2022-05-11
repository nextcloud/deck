<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\AppInfo;

use Closure;
use Exception;
use OC\EventDispatcher\SymfonyAdapter;
use OCA\Deck\Activity\CommentEventHandler;
use OCA\Deck\Capabilities;
use OCA\Deck\Collaboration\Resources\ResourceProvider;
use OCA\Deck\Collaboration\Resources\ResourceProviderCard;
use OCA\Deck\Dashboard\DeckWidget;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Event\AclCreatedEvent;
use OCA\Deck\Event\AclDeletedEvent;
use OCA\Deck\Event\AclUpdatedEvent;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Listeners\BeforeTemplateRenderedListener;
use OCA\Deck\Listeners\FullTextSearchEventListener;
use OCA\Deck\Middleware\DefaultBoardMiddleware;
use OCA\Deck\Middleware\ExceptionMiddleware;
use OCA\Deck\Notification\Notifier;
use OCA\Deck\Search\CardCommentProvider;
use OCA\Deck\Search\DeckProvider;
use OCA\Deck\Service\PermissionService;
use OCA\Deck\Sharing\DeckShareProvider;
use OCA\Deck\Sharing\Listener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Comments\CommentsEntityEvent;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\Server;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use OCP\Share\IManager;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'deck';

	public const COMMENT_ENTITY_TYPE = 'deckCard';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerUserGroupHooks']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCommentsEntity']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCommentsEventHandler']));
		$context->injectFn(Closure::fromCallable([$this, 'registerNotifications']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCollaborationResources']));

		$context->injectFn(function (IManager $shareManager) {
			$shareManager->registerShareProvider(DeckShareProvider::class);
		});

		$context->injectFn(function (Listener $listener, IEventDispatcher $eventDispatcher) {
			$listener->register($eventDispatcher);
		});
	}

	public function register(IRegistrationContext $context): void {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}

		$context->registerCapability(Capabilities::class);
		$context->registerMiddleWare(ExceptionMiddleware::class);
		$context->registerMiddleWare(DefaultBoardMiddleware::class);

		$context->registerService('databaseType', static function (ContainerInterface $c) {
			return $c->get(IConfig::class)->getSystemValue('dbtype', 'sqlite');
		});
		$context->registerService('database4ByteSupport', static function (ContainerInterface $c) {
			return $c->get(IDBConnection::class)->supports4ByteText();
		});

		$context->registerSearchProvider(DeckProvider::class);
		$context->registerSearchProvider(CardCommentProvider::class);
		$context->registerDashboardWidget(DeckWidget::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		
		// Event listening for full text search indexing
		$context->registerEventListener(CardCreatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(CardUpdatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(CardDeletedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(AclCreatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(AclUpdatedEvent::class, FullTextSearchEventListener::class);
		$context->registerEventListener(AclDeletedEvent::class, FullTextSearchEventListener::class);
	}

	public function registerNotifications(NotificationManager $notificationManager): void {
		$notificationManager->registerNotifierService(Notifier::class);
	}

	private function registerUserGroupHooks(IUserManager $userManager, IGroupManager $groupManager): void {
		$container = $this->getContainer();
		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $container->get(IEventDispatcher::class);
		// Delete user/group acl entries when they get deleted
		$eventDispatcher->addListener(UserDeletedEvent::class, static function (Event $event) use ($container): void {
			if (!($event instanceof UserDeletedEvent)) {
				return;
			}
			$user = $event->getUser();
			// delete existing acl entries for deleted user
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->get(AclMapper::class);
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_USER, $user->getUID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
			// delete existing user assignments
			$assignmentMapper = $container->get(AssignmentMapper::class);
			$assignments = $assignmentMapper->findByParticipant($user->getUID());
			foreach ($assignments as $assignment) {
				$assignmentMapper->delete($assignment);
			}

			/** @var BoardMapper $boardMapper */
			$boardMapper = $container->get(BoardMapper::class);
			$boards = $boardMapper->findAllByOwner($user->getUID());
			foreach ($boards as $board) {
				$boardMapper->delete($board);
			}
		});

		$eventDispatcher->addListener(GroupDeletedEvent::class, static function (Event $event) use ($container): void {
			if (!($event instanceof GroupDeletedEvent)) {
				return;
			}
			$group = $event->getGroup();
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->get(AclMapper::class);
			$aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
		});
	}

	public function registerCommentsEntity(IEventDispatcher $eventDispatcher): void {
		$eventDispatcher->addListener(CommentsEntityEvent::EVENT_ENTITY, function (CommentsEntityEvent $event) {
			$event->addEntityCollection(self::COMMENT_ENTITY_TYPE, function ($name) {
				/** @var CardMapper */
				$cardMapper = $this->getContainer()->get(CardMapper::class);
				/** @var PermissionService $permissionService */
				$permissionService = $this->getContainer()->get(PermissionService::class);

				try {
					return $permissionService->checkPermission($cardMapper, (int) $name, Acl::PERMISSION_READ);
				} catch (\Exception $e) {
					return false;
				}
			});
		});
	}

	protected function registerCommentsEventHandler(ICommentsManager $commentsManager): void {
		$commentsManager->registerEventHandler(function () {
			return $this->getContainer()->query(CommentEventHandler::class);
		});
	}

	protected function registerCollaborationResources(IProviderManager $resourceManager, SymfonyAdapter $symfonyAdapter): void {
		$resourceManager->registerResourceProvider(ResourceProvider::class);
		$resourceManager->registerResourceProvider(ResourceProviderCard::class);

		$symfonyAdapter->addListener('\OCP\Collaboration\Resources::loadAdditionalScripts', static function () {
			if (strpos(Server::get(IRequest::class)->getPathInfo(), '/call/') === 0) {
				// Talk integration has its own entrypoint which already includes collections handling
				return;
			}
			Util::addScript('deck', 'deck-collections');
		});
	}
}
