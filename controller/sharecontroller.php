<?php

namespace OCA\Deck\Controller;

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
        $groups = [];
        foreach ($this->groupManager->search($search, $limit, $offset) as $group) {
            $groups[] = $group->getGID();
        }
        $users = [];
        foreach ($this->userManager->searchDisplayName($search, $limit, $offset) as $user) {
            $users[] = $user->getDisplayName();
        }
        return array(
            'users' => $users,
            'groups' => $groups
        );
    }
}
