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

use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Acl;
use OCA\Deck\CardArchivedException;
use OCA\Deck\Db\StackMapper;


class CardService {

    private $cardMapper;

    public function __construct(CardMapper $cardMapper, StackMapper $stackMapper, PermissionService $permissionService) {
        $this->cardMapper = $cardMapper;
        $this->stackMapper = $stackMapper;
        $this->permissionService = $permissionService;
    }

    public function find($cardId) {
        $this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);
        return $this->cardMapper->find($cardId);
    }

    public function create($title, $stackId, $type, $order, $owner) {
        $this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);
        $card = new Card();
        $card->setTitle($title);
        $card->setStackId($stackId);
        $card->setType($type);
        $card->setOrder($order);
        $card->setOwner($owner);
        return $this->cardMapper->insert($card);

    }

    public function delete($id) {
        $this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
        return $this->cardMapper->delete($this->cardMapper->find($id));
    }

    public function update($id, $title, $stackId, $type, $order, $description, $owner) {
        $this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
        $card = $this->cardMapper->find($id);
        if ($card->getArchived()) {
            throw new CardArchivedException();
        }
        $card->setTitle($title);
        $card->setStackId($stackId);
        $card->setType($type);
        $card->setOrder($order);
        $card->setOwner($owner);
        $card->setDescription($description);
        return $this->cardMapper->update($card);
    }

    public function rename($id, $title) {
        $this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
        $card = $this->cardMapper->find($id);
        if ($card->getArchived()) {
            throw new CardArchivedException();
        }
        $card->setTitle($title);
        return $this->cardMapper->update($card);
    }

    public function reorder($id, $stackId, $order) {
        $this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
        $cards = $this->cardMapper->findAll($stackId);
        $result = [];
        $i = 0;
        foreach ($cards as $card) {
            if ($card->getArchived()) {
                throw new CardArchivedException();
            }
            if ($card->id === $id) {
                $card->setOrder($order);
                $card->setLastModified(time());
            }

            if ($i === $order)
                $i++;

            if ($card->id !== $id) {
                $card->setOrder($i++);
            }
            $this->cardMapper->update($card);
            $result[$card->getOrder()] = $card;
        }

        return $result;
    }

    public function archive($id) {
        $this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
        $card = $this->cardMapper->find($id);
        $card->setArchived(true);
        return $this->cardMapper->update($card);
    }

    public function unarchive($id) {
        $this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
        $card = $this->cardMapper->find($id);
        $card->setArchived(false);
        return $this->cardMapper->update($card);
    }

    public function assignLabel($cardId, $labelId) {
        $this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
        $card = $this->cardMapper->find($cardId);
        if ($card->getArchived()) {
            throw new CardArchivedException();
        }
        $this->cardMapper->assignLabel($cardId, $labelId);
    }

    public function removeLabel($cardId, $labelId) {
        $this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
        $card = $this->cardMapper->find($cardId);
        if ($card->getArchived()) {
            throw new CardArchivedException();
        }
        $this->cardMapper->removeLabel($cardId, $labelId);
    }
}