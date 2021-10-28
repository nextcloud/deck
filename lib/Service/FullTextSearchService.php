<?php

declare(strict_types=1);


/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Deck\Service;

use OC\FullTextSearch\Model\DocumentAccess;
use OC\FullTextSearch\Model\IndexDocument;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Provider\DeckProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\FullTextSearch\Model\IDocumentAccess;
use OCP\FullTextSearch\Model\IIndexDocument;

/**
 * Class FullTextSearchService
 *
 * @package OCA\Deck\Service
 */
class FullTextSearchService {


	/** @var BoardMapper */
	private $boardMapper;

	/** @var StackMapper */
	private $stackMapper;

	/** @var CardMapper */
	private $cardMapper;

	public function __construct(
		BoardMapper $boardMapper, StackMapper $stackMapper, CardMapper $cardMapper
	) {
		$this->boardMapper = $boardMapper;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
	}

	/**
	 * @param Card $card
	 *
	 * @return IIndexDocument
	 */
	public function generateIndexDocumentFromCard(Card $card): IIndexDocument {
		/** @psalm-var IIndexDocument */
		$document = new IndexDocument(DeckProvider::DECK_PROVIDER_ID, (string)$card->getId());

		return $document;
	}


	/**
	 * @param IIndexDocument $document
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function fillIndexDocument(IIndexDocument $document) {
		$card = $this->cardMapper->find((int)$document->getId());
		$document->setTitle(!empty($card->getTitle()) ? $card->getTitle() : '');
		$document->setContent(!empty($card->getDescription()) ? $card->getDescription() : '');
		$document->setAccess($this->generateDocumentAccessFromCardId((int)$card->getId()));
	}


	/**
	 * @param int $cardId
	 *
	 * @return IDocumentAccess
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function generateDocumentAccessFromCardId(int $cardId): IDocumentAccess {
		$board = $this->getBoardFromCardId($cardId);

		/** @psalm-var IDocumentAccess */
		return new DocumentAccess($board->getOwner());
	}


	/**
	 * @param string $userId
	 *
	 * @return Card[]
	 */
	public function getCardsFromUser(string $userId): array {
		$cards = [];
		$boards = $this->getBoardsFromUser($userId);
		foreach ($boards as $board) {
			$stacks = $this->getStacksFromBoard($board->getId());
			foreach ($stacks as $stack) {
				$cards = array_merge($cards, $this->getCardsFromStack($stack->getId()));
			}
		}

		return $cards;
	}


	/**
	 * @param int $boardId
	 *
	 * @return Card[]
	 */
	public function getCardsFromBoard(int $boardId): array {
		$cards = [];
		$stacks = $this->getStacksFromBoard($boardId);
		foreach ($stacks as $stack) {
			$cards = array_merge($cards, $this->getCardsFromStack($stack->getId()));
		}

		return $cards;
	}


	/**
	 * @param int $cardId
	 *
	 * @return Board
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getBoardFromCardId(int $cardId): Board {
		$boardId = (int)$this->cardMapper->findBoardId($cardId);
		/** @var Board $board */
		$board = $this->boardMapper->find($boardId);

		return $board;
	}


	/**
	 * @param int $stackId
	 *
	 * @return Card[]
	 */
	private function getCardsFromStack(int $stackId): array {
		return $this->cardMapper->findAll($stackId, null, null);
	}


	/**
	 * @param int $boardId
	 *
	 * @return Stack[]
	 */
	private function getStacksFromBoard(int $boardId): array {
		return $this->stackMapper->findAll($boardId, null, null);
	}


	/**
	 * @param string $userId
	 *
	 * @return Board[]
	 */
	private function getBoardsFromUser(string $userId): array {
		return $this->boardMapper->findAllByUser($userId, null, null, null);
	}
}
