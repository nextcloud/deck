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

use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCP\ILogger;
use OCP\IL10N;

use OCP\AppFramework\Utility\ITimeFactory;

use \OCA\Deck\Db\Stack;

use \OCA\Deck\Db\StackMapper;


class StackService  {

    private $stackMapper;
    private $cardMapper;
    private $logger;
    private $labelMapper;

    public function __construct(StackMapper $stackMapper, CardMapper $cardMapper, LabelMapper $labelMapper, ILogger $logger,
                                IL10N $l10n,
                                ITimeFactory $timeFactory) {
        $this->stackMapper = $stackMapper;
        $this->cardMapper = $cardMapper;
        $this->labelMapper = $labelMapper;
        $this->logger = $logger;
    }

    public function findAll($boardId) {
        $stacks = $this->stackMapper->findAll($boardId);
        $labels = $this->labelMapper->getAssignedLabelsForBoard($boardId);
        foreach ($stacks as $stackIndex => $stack) {
            $cards = $this->cardMapper->findAll($stack->id);
            foreach ($cards as $cardIndex => $card) {
            	if(array_key_exists($card->id, $labels)) {
                	$cards[$cardIndex]->setLabels($labels[$card->id]);
				}
            }
            $stacks[$stackIndex]->setCards($cards);
        }
        return $stacks;
    }

    public function findAllArchived($boardId) {
        $stacks = $this->stackMapper->findAll($boardId);
        $labels = $this->labelMapper->getAssignedLabelsForBoard($boardId);
        foreach ($stacks as $stackIndex => $stack) {
            $cards = $this->cardMapper->findAllArchived($stack->id);
            foreach ($cards as $cardIndex => $card) {
            	if(array_key_exists($card->id, $labels)) {
					$cards[$cardIndex]->setLabels($labels[$card->id]);
				}
            }
            $stacks[$stackIndex]->setCards($cards);
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

    public function delete($id) {
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