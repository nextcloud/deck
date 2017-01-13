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

namespace OCA\Deck\Service;

use OCA\Deck\Db\Label;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\LabelMapper;


class LabelService  {

    private $labelMapper;
    private $logger;

    public function __construct(
        LabelMapper $labelMapper,
        PermissionService $permissionService
    ) {
        $this->labelMapper = $labelMapper;
        $this->permissionService = $permissionService;
    }

    public function find($labelId) {
        $this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);
        $label = $this->labelMapper->find($labelId);
        return $label;
    }

    public function create($title, $color, $boardId) {
        $this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
        $label = new Label();
        $label->setTitle($title);
        $label->setColor($color);
        $label->setBoardId($boardId);
        return $this->labelMapper->insert($label);
    }

    public function delete($id) {
        $this->permissionService->checkPermission($this->labelMapper, $id, Acl::PERMISSION_MANAGE);
        return $this->labelMapper->delete($this->find($id));
    }

    public function update($id, $title, $color) {
        $this->permissionService->checkPermission($this->labelMapper, $id, Acl::PERMISSION_MANAGE);
        $label = $this->find($id);
        $label->setTitle($title);
        $label->setColor($color);
        return $this->labelMapper->update($label);
    }

}