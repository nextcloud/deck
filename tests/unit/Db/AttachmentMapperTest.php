<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use Test\AppFramework\Db\MapperTestUtility;

/**
 * @group DB
 */
class AttachmentMapperTest extends MapperTestUtility {

	/** @var IDBConnection */
	private $dbConnection;
	/** @var AttachmentMapper */
	private $attachmentMapper;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var CardMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $cardMapper;

	// Data
	private $attachments;
	private $attachmentsById = [];

	public function setup(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->cardMapper = $this->createMock(CardMapper::class);

		$this->dbConnection = Server::get(IDBConnection::class);
		$this->attachmentMapper = new AttachmentMapper(
			$this->dbConnection,
			$this->cardMapper,
			$this->userManager
		);
		$this->attachments = [
			$this->createAttachmentEntity(1, 'deck_file', 'file1.pdf'),
			$this->createAttachmentEntity(1, 'deck_file', 'file2.pdf'),
			$this->createAttachmentEntity(2, 'deck_file', 'file3.pdf'),
			$this->createAttachmentEntity(3, 'deck_file', 'file4.pdf')
		];
		foreach ($this->attachments as $attachment) {
			$entry = $this->attachmentMapper->insert($attachment);
			$entry->resetUpdatedFields();
			$this->attachmentsById[$entry->getId()] = $entry;
		}
	}

	private function createAttachmentEntity($cardId, $type, $data) {
		$attachment = new Attachment();
		$attachment->setCardId($cardId);
		$attachment->setType($type);
		$attachment->setData($data);
		$attachment->setCreatedBy('admin');
		return $attachment;
	}

	public function testFind() {
		foreach ($this->attachmentsById as $id => $attachment) {
			$this->assertEquals($attachment, $this->attachmentMapper->find($id));
		}
	}

	public function testFindAll() {
		$attachmentsByCard = [
			$this->attachmentMapper->findAll(1),
			$this->attachmentMapper->findAll(2),
			$this->attachmentMapper->findAll(3)
		];
		$this->assertEquals($attachmentsByCard[0], $this->attachmentMapper->findAll(1));
		$this->assertEquals($attachmentsByCard[1], $this->attachmentMapper->findAll(2));
		$this->assertEquals($attachmentsByCard[2], $this->attachmentMapper->findAll(3));
		$this->assertEquals([], $this->attachmentMapper->findAll(5));
	}

	public function testFindToDelete() {
		$attachmentsToDelete = $this->attachments;
		$attachmentsToDelete[0]->setDeletedAt(1);
		$attachmentsToDelete[2]->setDeletedAt(1);
		$this->attachmentMapper->update($attachmentsToDelete[0]);
		$this->attachmentMapper->update($attachmentsToDelete[2]);
		foreach ($attachmentsToDelete as $attachment) {
			$attachment->resetUpdatedFields();
		}

		$this->assertEquals([$attachmentsToDelete[0]], $this->attachmentMapper->findToDelete(1));
		$this->assertEquals([$attachmentsToDelete[2]], $this->attachmentMapper->findToDelete(2));
	}

	public function testIsOwner() {
		$this->cardMapper->expects($this->once())
			->method('isOwner')
			->with('admin', 1)
			->willReturn(true);
		$this->assertTrue($this->attachmentMapper->isOwner('admin', (string)$this->attachments[0]->getId()));
	}

	public function testIsOwnerInvalid() {
		$this->cardMapper->expects($this->once())
			->method('isOwner')
			->with('admin', 1)
			->will($this->throwException(new DoesNotExistException('does not exist')));
		$this->assertFalse($this->attachmentMapper->isOwner('admin', (string)$this->attachments[0]->getId()));
	}

	public function testFindBoardId() {
		$this->cardMapper->expects($this->any())
			->method('findBoardId')
			->willReturn(123);
		foreach ($this->attachmentsById as $attachment) {
			$this->assertEquals(123, $this->attachmentMapper->findBoardId($attachment->getId()));
		}
	}

	public function tearDown(): void {
		parent::tearDown();
		foreach ($this->attachments as $attachment) {
			$this->attachmentMapper->delete($attachment);
		}
	}
}
