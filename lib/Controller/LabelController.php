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

namespace OCA\Deck\Controller;

use OCA\Deck\Service\LabelService;

use OCP\IRequest;

use OCP\AppFramework\Controller;


class LabelController extends Controller {
    private $userId;
    private $labelService;
    public function __construct($appName,
                                IRequest $request,
                                LabelService $labelService,
                                $userId){
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->labelService = $labelService;
    }

    /**
     * @NoAdminRequired
     * @RequireManagePermission
     */
    public function create($title, $color, $boardId) {
        return $this->labelService->create($title, $this->userId, $color, $boardId);
    }
    /**
     * @NoAdminRequired
     * @RequireManagePermission
     */
    public function update($id, $title, $color) {
        return $this->labelService->update($id, $title, $this->userId, $color);
    }
    /**
     * @NoAdminRequired
     * @RequireManagePermission
     */
    public function delete($labelId) {
        return $this->labelService->delete($this->userId, $labelId);
    }

}
