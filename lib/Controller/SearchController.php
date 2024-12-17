<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	public function __construct(
		string $appName,
		IRequest $request,
		private SearchService $searchService,
	) {
		parent::__construct($appName, $request);
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
