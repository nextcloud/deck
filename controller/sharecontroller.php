<?php

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\AppFramework\ApiController as BaseApiController;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
class ShareController extends Controller {

    protected $userManager;
    protected $groupManager;
    public function __construct($appName,
                                IRequest $request,
                                IUserManager $userManager,
                                IGroupManager $groupManager
    ){
        parent::__construct($appName, $request);
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;

    }
    /**
     * FIXME: REMOVE, just for testing
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function searchUser($search) {
        $limit = null;
        $offset = null;
        $result = [];
        foreach ($this->groupManager->search($search, $limit, $offset) as $idx => $group) {
            $acl = new Acl();
            $acl->setType('group');
            $acl->setParticipant($group->getGID());
            $acl->setPermissionWrite(true);
            $acl->setPermissionInvite(true);
            $acl->setPermissionManage(true);
            $result[] = $acl;
        }
        foreach ($this->userManager->searchDisplayName($search, $limit, $offset) as $idx => $user) {
            $acl = new Acl();
            $acl->setType('user');
            $acl->setParticipant($user->getDisplayName());
            $acl->setPermissionWrite(true);
            $acl->setPermissionInvite(true);
            $acl->setPermissionManage(true);
            $result[] = $acl;
        }
        return $result;
    }
}
