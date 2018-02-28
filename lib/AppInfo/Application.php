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

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Notification\Notifier;
use OCP\AppFramework\App;
use OCA\Deck\Middleware\SharingMiddleware;
use OCP\IGroup;

use OCP\IUser;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\INavigationManager;

class Application extends App {

	/**
	 * Application constructor.
	 *
	 * @param array $urlParams
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(array $urlParams = array()) {
		parent::__construct('deck', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('SharingMiddleware', function() use ($server) {
			return new SharingMiddleware(
				$server->getLogger(),
				$server->getConfig()
			);
		});
		$container->registerMiddleWare('SharingMiddleware');

		$container->registerService('databaseType', function($container) {
			return $container->getServer()->getConfig()->getSystemValue('dbtype', 'sqlite');
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

	}

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
		}, function () {
			return ['id' => 'deck', 'name' => 'Deck'];
		});

	}
}
