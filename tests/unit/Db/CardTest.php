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

namespace OCA\Deck\Db;

use DateInterval;
use DateTime;
use OCA\Deck\Model\CardDetails;
use Test\TestCase;

class CardTest extends TestCase {
	private function createCard() {
		$card = new Card();
		$card->setId(1);
		$card->setTitle('My Card');
		$card->setDescription('a long description');
		$card->setStackId(1);
		$card->setType('text');
		$card->setLastModified(234);
		$card->setCreatedAt(123);
		$card->setOwner('admin');
		$card->setOrder(12);
		$card->setArchived(false);
		$card->setDone(null);
		// TODO: relation shared labels acl
		return $card;
	}

	private function createLabel() {
		$label = new Label();
		$label->setId(1);
		$label->setTitle('Label 1');
		$label->setColor('000000');
		return $label;
	}

	public function dataDuedate() {
		return [
			[(new DateTime()), Card::DUEDATE_NOW],
			[(new DateTime())->sub(new DateInterval('P1D')), Card::DUEDATE_OVERDUE],
			[(new DateTime())->add(new DateInterval('P1D')), Card::DUEDATE_NEXT],
			[(new DateTime())->add(new DateInterval('P2D')), Card::DUEDATE_FUTURE]
		];
	}

	/**
	 * @dataProvider dataDuedate
	 */
	public function testDuedate(DateTime $duedate, $state) {
		$card = $this->createCard();
		$card->setDuedate($duedate->format('Y-m-d H:i:s'));
		$this->assertEquals($state, (new CardDetails($card))->jsonSerialize()['overdue']);
	}

	public function testJsonSerialize() {
		$card = $this->createCard();
		$this->assertEquals([
			'id' => 1,
			'title' => 'My Card',
			'description' => 'a long description',
			'type' => 'text',
			'lastModified' => 234,
			'createdAt' => 123,
			'owner' => 'admin',
			'order' => 12,
			'stackId' => 1,
			'labels' => null,
			'duedate' => null,
			'overdue' => 0,
			'archived' => false,
			'attachments' => null,
			'attachmentCount' => null,
			'assignedUsers' => null,
			'deletedAt' => 0,
			'commentsUnread' => 0,
			'commentsCount' => 0,
			'lastEditor' => null,
			'ETag' => $card->getETag(),
			'done' => null,
		], (new CardDetails($card))->jsonSerialize());
	}
	public function testJsonSerializeLabels() {
		$card = $this->createCard();
		$card->setLabels([]);
		$this->assertEquals([
			'id' => 1,
			'title' => 'My Card',
			'description' => 'a long description',
			'type' => 'text',
			'lastModified' => 234,
			'createdAt' => 123,
			'owner' => 'admin',
			'order' => 12,
			'stackId' => 1,
			'labels' => [],
			'duedate' => null,
			'overdue' => 0,
			'archived' => false,
			'attachments' => null,
			'attachmentCount' => null,
			'assignedUsers' => null,
			'deletedAt' => 0,
			'commentsUnread' => 0,
			'commentsCount' => 0,
			'lastEditor' => null,
			'ETag' => $card->getETag(),
			'done' => false,
		], (new CardDetails($card))->jsonSerialize());
	}

	public function testJsonSerializeAsignedUsers() {
		$card = $this->createCard();
		$card->setAssignedUsers([ 'user1' ]);
		$card->setLabels([]);
		$this->assertEquals([
			'id' => 1,
			'title' => 'My Card',
			'description' => 'a long description',
			'type' => 'text',
			'lastModified' => 234,
			'createdAt' => 123,
			'owner' => 'admin',
			'order' => 12,
			'stackId' => 1,
			'labels' => [],
			'duedate' => null,
			'overdue' => 0,
			'archived' => false,
			'attachments' => null,
			'attachmentCount' => null,
			'assignedUsers' => ['user1'],
			'deletedAt' => 0,
			'commentsUnread' => 0,
			'commentsCount' => 0,
			'lastEditor' => null,
			'ETag' => $card->getETag(),
			'done' => false,
		], (new CardDetails($card))->jsonSerialize());
	}

	public function testToEventDataSerializationSimple() {
		$card = $this->createCard();
		$board = new Board();
		$board->setId(1);
		$card->setRelatedBoard($board);

		$lastmodified = new DateTime('2024-01-01 00:00:00');
		$card->setLastModified($lastmodified->getTimestamp());
		$created = new DateTime('2024-01-02 00:00:00');
		$card->setCreatedAt($created->getTimestamp());

		$this->assertEquals([
			'title' => 'My Card',
			'description' => 'a long description',
			'boardId' => 1,
			'stackId' => 1,
			'lastModified' => $lastmodified->format(DATE_ATOM),
			'createdAt' => $created->format(DATE_ATOM),
			'labels' => [],
			'assignedUsers' => [],
			'order' => 12,
			'archived' => false,
			'commentsUnread' => 0,
			'commentsCount' => 0,
			'owner' => 'admin',
			'lastEditor' => null,
			'duedate' => null,
			'doneAt' => null,
			'deletedAt' => null,
		], $card->toEventData()->jsonSerialize());
	}

	public function testToEventDataSerializationFull() {
		$card = $this->createCard();

		$board = new Board();
		$board->setId(1);
		$card->setRelatedBoard($board);

		$card->setAssignedUsers([ 'user1' ]);
		$card->setLabels([$this->createLabel()]);
		$card->setLastEditor('someuser');
		$card->setArchived(true);

		$lastModified = new DateTime('2024-01-01 00:00:00');
		$card->setLastModified($lastModified->getTimestamp());
		$createdAt = new DateTime('2024-01-02 00:00:00');
		$card->setCreatedAt($createdAt->getTimestamp());
		$doneAt = new DateTime('2024-01-03 00:00:00');
		$card->setDone($doneAt);
		$duedate = new DateTime('2024-01-05 00:00:00');
		$card->setDuedate($duedate);
		$deletedAt = new DateTime('2024-01-05 00:00:00');
		$card->setDeletedAt($deletedAt->getTimestamp());

		$this->assertEquals([
			'title' => 'My Card',
			'description' => 'a long description',
			'boardId' => 1,
			'stackId' => 1,
			'lastModified' => $lastModified->format(DATE_ATOM),
			'createdAt' => $createdAt->format(DATE_ATOM),
			'labels' => [
				[
					'id' => 1,
					'title' => 'Label 1'
				]
			],
			'assignedUsers' => ['user1'],
			'order' => 12,
			'archived' => true,
			'commentsUnread' => 0,
			'commentsCount' => 0,
			'doneAt' => $doneAt->format(DATE_ATOM),
			'owner' => 'admin',
			'lastEditor' => 'someuser',
			'duedate' => $duedate->format(DATE_ATOM),
			'deletedAt' => $deletedAt->format(DATE_ATOM),
		], $card->toEventData()->jsonSerialize());
	}
}
