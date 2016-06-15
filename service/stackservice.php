<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\CardMapper;
use OCP\ILogger;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

use \OCA\Deck\Db\Stack;
use \OCA\Deck\Db\StackMapper;


class StackService  {

    private $stackMapper;
    private $cardMapper;
    private $logger;
    private $l10n;
    private $timeFactory;

    public function __construct(StackMapper $stackMapper, CardMapper $cardMapper,ILogger $logger,
                                IL10N $l10n,
                                ITimeFactory $timeFactory) {
        $this->stackMapper = $stackMapper;
        $this->cardMapper = $cardMapper;
        $this->logger = $logger;
    }

    public function findAll($boardId) {
        $stacks = $this->stackMapper->findAll($boardId);
        foreach ($stacks as $idx => $s) {
            $stacks[$idx]->setCards($this->cardMapper->findAll($s->id));
        }
        return $stacks;
    }

    public function create($title, $boardId, $order) {
        $stack = new Stack();
        $stack->setTitle($title);
        $stack->setBoardId($boardId);
        $stack->setOrder($order);
        return $this->stackMapper->insert($stack);

    }

    public function delete($userId, $id) {
        return $this->stackMapper->delete($this->stackMapper->find($id));
    }

    public function update($id, $title, $boardId, $order) {
        $stack = $this->stackMapper->find($id);
        $stack->setTitle($title);
        $stack->setBoardId($boardId);
        $stack->setOrder($order);
        return $this->stackMapper->update($stack);
    }
}