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


class LabelService  {

    private $labelMapper;
    private $logger;
    private $l10n;
    private $timeFactory;

    public function __construct(ILogger $logger,
                                IL10N $l10n,
                                ITimeFactory $timeFactory,
                                LabelMapper $labelMapper) {
        $this->labelMapper = $labelMapper;
        $this->logger = $logger;
    }

    public function find($userId, $labelId) {
        $label = $this->labelMapper->find($labelId);
        // FIXME: [share] Check for user permissions
        return $label;
    }

    public function create($title, $userId, $color, $boardId) {
        $label = new Label();
        $label->setTitle($title);
        $label->setColor($color);
        $label->setBoardId($boardId);
        return $this->labelMapper->insert($label);
    }

    public function delete($userId, $id) {
        return $this->labelMapper->delete($this->find($userId, $id));
    }

    public function update($id, $title, $userId, $color) {
        $label = $this->find($userId, $id);
        $label->setTitle($title);
        $label->setColor($color);
        return $this->labelMapper->update($label);
    }

}