<?php
/**
 * @copyright Copyright (c) 2022 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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
 */

namespace OCA\Deck\Reference;

use OC\Collaboration\Reference\Reference;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\CardService;
use OCA\Deck\Service\StackService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\IURLGenerator;

class CardReferenceProvider implements IReferenceProvider {
	private CardService $cardService;
	private IURLGenerator $urlGenerator;
	private BoardService $boardService;
	private StackService $stackService;

	public function __construct(CardService $cardService,
								BoardService $boardService,
								StackService $stackService,
								IURLGenerator $urlGenerator,
								?string $userId) {
		$this->cardService = $cardService;
		$this->urlGenerator = $urlGenerator;
		$this->boardService = $boardService;
		$this->stackService = $stackService;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example: https://nextcloud.local/index.php/apps/deck/#/board/2/card/11
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/#\/board\/[0-9]+\/card\/[0-9]+$/', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/board\/[0-9]+\/card\/[0-9]+$/', $referenceText) === 1;

		return $noIndexMatch || $indexMatch;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$cardIds = $this->getBoardCardId($referenceText);
			if ($cardIds !== null) {
				[$boardId, $cardId] = $cardIds;
				$card = $this->cardService->find((int) $cardId);
				$board = $this->boardService->find((int) $boardId);
				$stack = $this->stackService->find((int) $card->jsonSerialize()['stackId']);
				$reference = new Reference($referenceText);
				$reference->setRichObject(Application::APP_ID . '-card', [
					'card' => $card,
					'board' => $board,
					'stack' => $stack,
				]);
				return $reference;
			}
		}

		return null;
	}

	private function getBoardCardId(string $url): ?array {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '\/#\/board\/([0-9]+)\/card\/([0-9]+)$/', $url, $matches);
		if ($matches && count($matches) > 2) {
			return [$matches[1], $matches[2]];
		}

		preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/board\/([0-9]+)\/card\/([0-9]+)$/', $url, $matches2);
		if ($matches2 && count($matches2) > 2) {
			return [$matches2[1], $matches2[2]];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		return $referenceId;
	}

	public function getCacheKey(string $referenceId): ?string {
		return null;
	}
}
