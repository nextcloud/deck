<?php
/*
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\Deck\Sharing;


use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Card;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\PermissionService;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Share\IShare;

class DeckPlugin implements ISearchPlugin {

	/**
	 * @var BoardService
	 */
	private $boardService;
	/**
	 * @var CardService
	 */
	private $cardService;

	public function __construct(BoardService $boardService, CardService $cardService) {
		$this->boardService = $boardService;
		$this->cardService = $cardService;
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		$result = ['wide' => [], 'exact' => []];

		$cards = $this->cardService->searchRaw($search, $limit, $offset);
		/** @var PermissionService $permissionsService */
		$permissionsService = \OC::$server->get(PermissionService::class);
		foreach ($cards as $card) {
			try {
				$permissionsService->checkPermission(null, $card['board_id'], Acl::PERMISSION_EDIT);
			} catch (NoPermissionException $e) {
				continue;
			}
			$board = $this->boardService->find($card['board_id']);

			$result['wide'][] = [
				'label' => $card['title'],
				'value' => [
					'shareType' => IShare::TYPE_DECK,
					'shareWith' => (string)$card['id']
				],
				'shareWithDescription' => $board->getTitle() . ' – ' . $card['stack_title'],
			];
		}
		$type = new SearchResultType('deck');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);
		return false;
	}
}
