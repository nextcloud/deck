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

use Exception;
use OC_Util;
use OCA\Deck\Activity\CommentEventHandler;
use OCA\Deck\Capabilities;
use OCA\Deck\Collaboration\Resources\ResourceProvider;
use OCA\Deck\Collaboration\Resources\ResourceProviderCard;
use OCA\Deck\Dashboard\DeckWidget;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Middleware\DefaultBoardMiddleware;
use OCA\Deck\Middleware\ExceptionMiddleware;
use OCA\Deck\Notification\Notifier;
use OCA\Deck\Search\DeckProvider;
use OCA\Deck\Service\FullTextSearchService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Comments\CommentsEntityEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\IConfig;
use OCP\IContainer;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Util;

class Application20 extends App implements IBootstrap {
	public const APP_ID = 'deck';

	public const COMMENT_ENTITY_TYPE = 'deckCard';

	/** @var IServerContainer */
	private $server;

	/** @var FullTextSearchService */
	private $fullTextSearchService;

	/** @var IFullTextSearchManager */
	private $fullTextSearchManager;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$this->server = \OC::$server;
	}

	public function boot(IBootContext $context): void {
		$notificationManager = $context->getServerContainer()->get(\OCP\Notification\IManager::class);
		$notificationManager->registerNotifierService(Notifier::class);
		\OCP\Util::addStyle('deck', 'deck');
	}

	public function register(IRegistrationContext $context): void {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}

		$context->registerCapability(Capabilities::class);
		$context->registerMiddleWare(ExceptionMiddleware::class);
		$context->registerMiddleWare(DefaultBoardMiddleware::class);

		$context->registerService('databaseType', static function (IContainer $c) {
			return $c->get(IConfig::class)->getSystemValue('dbtype', 'sqlite');
		});
		$context->registerService('database4ByteSupport', static function (IContainer $c) {
			return $c->get(IDBConnection::class)->supports4ByteText();
		});

		$context->registerSearchProvider(DeckProvider::class);

		$context->registerDashboardWidget(DeckWidget::class);

		$this->registerUserGroupHooks();

		$this->registerCommentsEntity();
		$this->registerFullTextSearch();
		$this->registerCollaborationResources();
	}

	private function registerUserGroupHooks(): void {
		$container = $this->getContainer();
		// Delete user/group acl entries when they get deleted
		/** @var IUserManager $userManager */
		$userManager = $this->server->getUserManager();
		$userManager->listen('\OC\User', 'postDelete', static function (IUser $user) use ($container) {
			// delete existing acl entries for deleted user
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->query(AclMapper::class);
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_USER, $user->getUID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
			// delete existing user assignments
			$assignmentMapper = $container->query(AssignedUsersMapper::class);
			$assignments = $assignmentMapper->findByUserId($user->getUID());
			foreach ($assignments as $assignment) {
				$assignmentMapper->delete($assignment);
			}

			/** @var BoardMapper $boardMapper */
			$boardMapper = $container->query(BoardMapper::class);
			$boards = $boardMapper->findAllByOwner($user->getUID());
			foreach ($boards as $board) {
				$boardMapper->delete($board);
			}
		});

		/** @var IUserManager $userManager */
		$groupManager = $this->server->getGroupManager();
		$groupManager->listen('\OC\Group', 'postDelete', static function (IGroup $group) use ($container) {
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->query(AclMapper::class);
			$aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
		});
	}

	public function registerCommentsEntity(): void {
		$this->server->getEventDispatcher()->addListener(CommentsEntityEvent::EVENT_ENTITY, function (CommentsEntityEvent $event) {
			$event->addEntityCollection(self::COMMENT_ENTITY_TYPE, function ($name) {
				/** @var CardMapper */
				$cardMapper = $this->getContainer()->query(CardMapper::class);
				$permissionService = $this->getContainer()->query(PermissionService::class);

				try {
					return $permissionService->checkPermission($cardMapper, (int) $name, Acl::PERMISSION_READ);
				} catch (\Exception $e) {
					return false;
				}
			});
		});
		$this->registerCommentsEventHandler();
	}

	protected function registerCommentsEventHandler(): void {
		$this->server->getCommentsManager()->registerEventHandler(function () {
			return $this->getContainer()->query(CommentEventHandler::class);
		});
	}

	protected function registerCollaborationResources(): void {
		$version = OC_Util::getVersion()[0];
		/**
		 * Register Collaboration ResourceProvider
		 *
		 * @Todo: Remove if min-version is 18
		 */
		if ($version < 18) {
			/** @var IManager $resourceManager */
			$resourceManager = $this->getContainer()->query(IManager::class);
		} else {
			/** @var IProviderManager $resourceManager */
			$resourceManager = $this->getContainer()->query(IProviderManager::class);
		}
		$resourceManager->registerResourceProvider(ResourceProvider::class);
		$resourceManager->registerResourceProvider(ResourceProviderCard::class);

		$this->server->getEventDispatcher()->addListener('\OCP\Collaboration\Resources::loadAdditionalScripts', static function () {
			Util::addScript('deck', 'collections');
		});
	}

	public function registerFullTextSearch(): void {
		if (Util::getVersion()[0] < 16) {
			return;
		}

		$c = $this->getContainer();
		try {
			$this->fullTextSearchService = $c->query(FullTextSearchService::class);
			$this->fullTextSearchManager = $c->query(IFullTextSearchManager::class);
		} catch (Exception $e) {
			return;
		}

		if (!$this->fullTextSearchManager->isAvailable()) {
			return;
		}

		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $this->server->query(IEventDispatcher::class);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onCreate', function (Event $e) {
				$this->fullTextSearchService->onCardCreated($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onUpdate', function (Event $e) {
				$this->fullTextSearchService->onCardUpdated($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onDelete', function (Event $e) {
				$this->fullTextSearchService->onCardDeleted($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareNew', function (Event $e) {
				$this->fullTextSearchService->onBoardShares($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareEdit', function (Event $e) {
				$this->fullTextSearchService->onBoardShares($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareDelete', function (Event $e) {
				$this->fullTextSearchService->onBoardShares($e);
			}
		);
	}
}
