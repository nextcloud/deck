<?php

namespace OCA\Deck\Service;

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

use \OCA\Deck\Db\Board;
use \OCA\Deck\Db\BoardMapper;


class BoardService  {

    private $boardMapper;
    private $logger;
    private $l10n;
    private $timeFactory;

    public function __construct(BoardMapper $boardMapper, ILogger $logger,
                                IL10N $l10n,
                                ITimeFactory $timeFactory) {
        $this->boardMapper = $boardMapper;
        $this->logger = $logger;
    }

    public function findAll($userId) {
        return $this->boardMapper->findAll($userId);
    }

    public function find($userId, $boardId) {
        $board = $this->boardMapper->find($boardId);
        if($board->getOwner() === $userId)
            return $board;
        else
            return null;

        // FIXME: [share] Check for user permissions

    }

    public function create($title, $userId, $color) {
        $board = new Board();
        $board->setTitle($title);
        $board->setOwner($userId);
        $board->setColor($color);
        return $this->boardMapper->insert($board);

    }

    public function delete($userId, $id) {
        return $this->boardMapper->delete($this->find($userId, $id));
    }

    public function update($id, $title, $userId, $color) {
        $board = $this->find($userId, $id);
        $board->setTitle($title);
        $board->setColor($color);
        return $this->boardMapper->update($board);
    }
}