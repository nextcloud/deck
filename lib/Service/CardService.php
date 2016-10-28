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

use \OCA\Deck\Db\Card;
use \OCA\Deck\Db\CardMapper;
use \OCA\Deck\CardArchivedException;


class CardService  {

    private $cardMapper;

    public function __construct(CardMapper $cardMapper) {
        $this->cardMapper = $cardMapper;
    }

    public function find($cardId) {
        return $this->cardMapper->find($cardId);
    }
    public function create($title, $stackId, $type, $order, $owner) {
        $card = new Card();
        $card->setTitle($title);
        $card->setStackId($stackId);
        $card->setType($type);
        $card->setOrder($order);
        $card->setOwner($owner);
        return $this->cardMapper->insert($card);

    }

    public function delete($id) {
        return $this->cardMapper->delete($this->cardMapper->find($id));
    }

    public function update($id, $title, $stackId, $type, $order, $description, $owner) {
        $card = $this->cardMapper->find($id);
        if($card->getArchived()) {
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
        $card = $this->cardMapper->find($id);
        if($card->getArchived()) {
            throw new CardArchivedException();
        }
        $card->setTitle($title);
        return $this->cardMapper->update($card);
    }
    public function reorder($id, $stackId, $order) {
        $cards = $this->cardMapper->findAll($stackId);
        $i = 0;
        foreach ($cards as $card) {
            if($card->getArchived()) {
                throw new CardArchivedException();
            }
            if($card->id === $id) {
                $card->setOrder($order);
				$card->setLastModified(time());
			}

            if($i === $order)
                $i++;

            if($card->id !== $id) {
                $card->setOrder($i++);
            }
            $this->cardMapper->update($card);
        }
        // FIXME: return reordered cards without an additional db query
        //$cards = $this->cardMapper->findAll($stackId);
        return $cards;
    }

    public function archive($id) {
        $card = $this->cardMapper->find($id);
        $card->setArchived(true);
        return $this->cardMapper->update($card);
    }
    
    public function unarchive($id) {
        $card = $this->cardMapper->find($id);
        $card->setArchived(false);
        return $this->cardMapper->update($card);
    }

    public function assignLabel($cardId, $labelId) {
        $card = $this->cardMapper->find($cardId);
        if($card->getArchived()) {
            throw new CardArchivedException();
        }
        $this->cardMapper->assignLabel($cardId, $labelId);
    }

    public function removeLabel($cardId, $labelId) {
        $card = $this->cardMapper->find($cardId);
        if($card->getArchived()) {
            throw new CardArchivedException();
        }
        $this->cardMapper->removeLabel($cardId, $labelId);
    }
}