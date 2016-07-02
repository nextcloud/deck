<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Label;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

use \OCA\Deck\Db\Board;
use \OCA\Deck\Db\BoardMapper;
use \OCA\Deck\Db\LabelMapper;


class BoardService  {

    private $boardMapper;
    private $labelMapper;
    private $logger;
    private $l10n;
    private $timeFactory;

    public function __construct(BoardMapper $boardMapper, ILogger $logger,
                                IL10N $l10n,
                                ITimeFactory $timeFactory,
                                LabelMapper $labelMapper) {
        $this->boardMapper = $boardMapper;
        $this->labelMapper = $labelMapper;
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
        $new_board = $this->boardMapper->insert($board);

        // create new labels
        $default_labels = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];
        $labels = [];
        foreach ($default_labels as $color) {
            $label = new Label();
            $label->setColor($color);
            $label->setBoardId($new_board->getId());
            $labels[] = $this->labelMapper->insert($label);
        }
        $new_board->setLabels($labels);
        return $new_board;

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

    public function labels($boardId) {
        
    }
}