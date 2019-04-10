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
use OCA\Deck\Activity\CommentEventHandler;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Middleware\ExceptionMiddleware;
use OCA\Deck\Notification\Notifier;
use OCA\Deck\Service\FullTextSearchService;
use OCP\AppFramework\App;
use OCA\Deck\Middleware\SharingMiddleware;
use OCP\Collaboration\Resources\IManager;
use OCP\Comments\CommentsEntityEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\INavigationManager;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {


	/** @var FullTextSearchService */
	private $fullTextSearchService;


	/**
	 * Application constructor.
	 *
	 * @param array $urlParams
	 *
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(array $urlParams = array()) {
		parent::__construct('deck', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('ExceptionMiddleware', function() use ($server) {
			return new ExceptionMiddleware(
				$server->getLogger(),
				$server->getConfig()
			);
		});
		$container->registerMiddleWare('ExceptionMiddleware');

		$container->registerService('databaseType', function($container) {
			return $container->getServer()->getConfig()->getSystemValue('dbtype', 'sqlite');
		});

		$container->registerService('database4ByteSupport', function($container) {
			return $container->getServer()->getDatabaseConnection()->supports4ByteText();
		});

		// Delete user/group acl entries when they get deleted
		/** @var IUserManager $userManager */
		$userManager = $server->getUserManager();
		$userManager->listen('\OC\User', 'postDelete', function(IUser $user) use ($container) {
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
		});

		/** @var IUserManager $userManager */
		$groupManager = $server->getGroupManager();
		$groupManager->listen('\OC\Group', 'postDelete', function(IGroup $group) use ($container) {
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->query(AclMapper::class);
			$aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
		});

		$this->registerCollaborationResources();

	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function registerNavigationEntry() {
		$container = $this->getContainer();
		$container->query(INavigationManager::class)->add(function() use ($container) {
			$urlGenerator = $container->query(IURLGenerator::class);
			return [
				'id' => 'deck',
				'order' => 10,
				'href' => $urlGenerator->linkToRoute('deck.page.index'),
				'icon' => $urlGenerator->imagePath('deck', 'deck.svg'),
				'name' => 'Deck',
			];
		});
	}

	public function registerNotifications() {
		$notificationManager = \OC::$server->getNotificationManager();
		$self = &$this;
		$notificationManager->registerNotifier(function() use (&$self) {
			return $self->getContainer()->query(Notifier::class);
		}, function() {
			return ['id' => 'deck', 'name' => 'Deck'];
		});
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function registerCommentsEntity() {
		$this->getContainer()->getServer()->getEventDispatcher()->addListener(CommentsEntityEvent::EVENT_ENTITY, function(CommentsEntityEvent $event) {
			$event->addEntityCollection('deckCard', function($name) {
				/** @var CardMapper */
				$service = $this->getContainer()->query(CardMapper::class);
				try {
					$service->find((int) $name);
				} catch (\InvalidArgumentException $e) {
					return false;
				}
				return true;
			});
		});
		$this->registerCommentsEventHandler();
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	protected function registerCommentsEventHandler() {
		$this->getContainer()->getServer()->getCommentsManager()->registerEventHandler(function () {
			return $this->getContainer()->query(CommentEventHandler::class);
		});
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	protected function registerCollaborationResources() {
		$version = \OC_Util::getVersion()[0];
		if ($version < 16) {
			return;
		}

		/**
		 * Register Collaboration ResourceProvider
		 */
		/** @var IManager $resourceManager */
		$resourceManager = $this->getContainer()->query(IManager::class);
		$resourceManager->registerResourceProvider(\OCA\Deck\Collaboration\Resources\ResourceProvider::class);
		\OC::$server->getEventDispatcher()->addListener('\OCP\Collaboration\Resources::loadAdditionalScripts', function () {
			\OCP\Util::addScript('deck', 'build/collections');
		});
	}

	public function registerFullTextSearch() {
		if (Util::getVersion()[0] < 16 || !\OC::$server->getAppManager()->isEnabledForUser('fulltextsearch')) {
			return;
		}

		$c = $this->getContainer();
		try {
			$this->fullTextSearchService = $c->query(FullTextSearchService::class);
		} catch (Exception $e) {
			return;
		}

		$eventDispatcher = \OC::$server->getEventDispatcher();
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onCreate', function(GenericEvent $e) {
			$this->fullTextSearchService->onCardCreated($e);
		}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onUpdate', function(GenericEvent $e) {
			$this->fullTextSearchService->onCardUpdated($e);
		}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onDelete', function(GenericEvent $e) {
			$this->fullTextSearchService->onCardDeleted($e);
		}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareNew', function(GenericEvent $e) {
			$this->fullTextSearchService->onBoardShares($e);
		}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareEdit', function(GenericEvent $e) {
			$this->fullTextSearchService->onBoardShares($e);
		}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareDelete', function(GenericEvent $e) {
			$this->fullTextSearchService->onBoardShares($e);
		}
		);
	}

}
