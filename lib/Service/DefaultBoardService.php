<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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

use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\StackService;
use OCA\Deck\Service\CardService;

class DefaultBoardService {

    protected $boardService;
    protected $stackService;
    protected $cardService;

    public function __construct(BoardService $boardService, StackService $stackService, CardService $cardService) {
        $this->boardService = $boardService;
        $this->stackService = $stackService;
    }

    
    public function checkFirstRun($userId) {
        // Add a user config value like 'firstrun' to check if the default board 
        // has already been created for the user

        // TODO: Remove hardcode once I figure out how to do the config value.
        return true;
    }

    public function createDefaultBoard($title, $userId, $color) {
        $defaultBoard = $this->boardService->create($title, $userId, $color);
        $defaultStacks = [];
        $defaultCards = [];
        
        $boardId = $defaultBoard->getId();
                        
        $defaultStacks[] = $this->stackService->create('To do', $boardId, 1);
        $defaultStacks[] = $this->stackService->create('Doing', $boardId, 1);
        $defaultStacks[] = $this->stackService->create('Done', $boardId, 1);
        
        $defaultCards[] = $this->cardService->create('Example Task 3', $stacks[0]->getId(), 'text', 0, $userId);
        $defaultCards[] = $this->cardService->create('Example Task 2', $stacks[1]->getId(), 'text', 0, $userId);
        $defaultCards[] = $this->cardService->create('Example Task 1', $stacks[2]->getId(), 'text', 0, $userId);
    }
}