<?php

namespace OCA\Deck\Service;

use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

use \OCA\Deck\Db\Stack;
use \OCA\Deck\Db\StackMapper;


class StackService  {

    private $stackMapper;
    private $logger;
    private $l10n;
    private $timeFactory;

    public function __construct(StackMapper $stackMapper, ILogger $logger,
                                IL10N $l10n,
                                ITimeFactory $timeFactory) {
        $this->stackMapper = $stackMapper;
        $this->logger = $logger;
    }

    public function findAll($boardId) {
        return $this->stackMapper->findAll($boardId);
    }

    public function create($title, $boardId, $order) {
        $stack = new Stack();
        $stack->setTitle($title);
        $stack->setBoardId($boardId);
        $stack->setOrder($order);
        return $this->stackMapper->insert($stack);

    }

    public function delete($userId, $id) {
        return $this->stackMapper->delete($this->find($userId, $id));
    }

    public function update($id, $title, $boardId, $order) {
        $stack = $this->find($id);
        $stack->setTitle($title);
        $stack->setBoardId($boardId);
        $stack->setOrder($order);
        return $this->stackMapper->update($stack);
    }
}