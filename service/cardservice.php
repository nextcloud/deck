<?php

namespace OCA\Deck\Service;

use OC\OCS\Exception;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

use \OCA\Deck\Db\Card;
use \OCA\Deck\Db\CardMapper;


class CardService  {

    private $cardMapper;
    private $logger;


    public function __construct(CardMapper $cardMapper) {
        $this->cardMapper = $cardMapper;
    }

    public function find($userId, $cardId) {
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

    public function delete($userId, $id) {
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
            }

            if($i === $order)
                $i++;

            if($card->id !== $id) {
                $card->setOrder($i++);
            }
            $card->setLastModified(time());
            $this->cardMapper->update($card);
        }
        // FIXME: return reordered cards without an additional db query
        $cards = $this->cardMapper->findAll($stackId);
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

    public function assignLabel($userId, $cardId, $labelId) {
        $card = $this->cardMapper->find($cardId);
        if($card->getArchived()) {
            throw new CardArchivedException();
        }
        $this->cardMapper->assignLabel($cardId, $labelId);
    }

    public function removeLabel($userId, $cardId, $labelId) {
        $card = $this->cardMapper->find($cardId);
        if($card->getArchived()) {
            throw new CardArchivedException();
        }
        $this->cardMapper->removeLabel($cardId, $labelId);
    }
}