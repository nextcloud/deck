<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
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


namespace OCA\Deck\Controller;

use OCA\Deck\Db\Card;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Service\SearchService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class SearchController extends OCSController {

	/**
	 * @var SearchService
	 */
	private $searchService;

	public function __construct(string $appName, IRequest $request, SearchService $searchService) {
		parent::__construct($appName, $request);
		$this->searchService = $searchService;
	}

	/**
	 * @NoAdminRequired
	 */
	public function search(string $term, ?int $limit = null, ?int $cursor = null): DataResponse {
		$cards = $this->searchService->searchCards($term, $limit, $cursor);
		return new DataResponse(array_map(function (Card $card) {
			$board = $card->getRelatedBoard();
			$json = (new CardDetails($card, $board))->jsonSerialize();

			$json['relatedBoard'] = $board;
			$json['relatedStack'] = $card->getRelatedStack();

			return $json;
		}, $cards));
	}
}
