<?php

namespace OCA\Deck\Db;

use PHPUnit\Framework\TestCase;

class BoardTest extends TestCase {
	private function createBoard() {
		$board = new Board();
		$board->setId(1);
		$board->setTitle("My Board");
		$board->setOwner("admin");
		$board->setColor("000000");
		$board->setArchived(false);
		// TODO: relation shared labels acl
		return $board;
	}
	public function testJsonSerialize() {
		$board = $this->createBoard();
		$board->setUsers(['user1', 'user2']);
		$this->assertEquals([
			'id' => 1,
			'title' => "My Board",
			'owner' => "admin",
			'color' => "000000",
			'labels' => array(),
			'permissions' => [],
			'deletedAt' => 0,
			'acl' => array(),
			'archived' => false,
			'users' => ['user1', 'user2'],
		], $board->jsonSerialize());
	}

	public function testSetLabels() {
		$board = $this->createBoard();
		$board->setLabels(array("foo", "bar"));
		$this->assertEquals([
			'id' => 1,
			'title' => "My Board",
			'owner' => "admin",
			'color' => "000000",
			'labels' => array("foo", "bar"),
			'permissions' => [],
			'deletedAt' => 0,
			'acl' => array(),
			'archived' => false,
			'users' => [],
		], $board->jsonSerialize());
	}
	public function testSetAcl() {
		$acl = new Acl();
		$acl->setId(1);
		$board = $this->createBoard();
		$board->setAcl(array($acl));
		$result = $board->getAcl()[1];
		$this->assertEquals($acl, $result);
	}
	public function testSetShared() {
		$board = $this->createBoard();
		$board->setShared(1);
		$this->assertEquals([
			'id' => 1,
			'title' => "My Board",
			'owner' => "admin",
			'color' => "000000",
			'labels' => array(),
			'permissions' => [],
			'deletedAt' => 0,
			'acl' => array(),
			'archived' => false,
			'shared' => 1,
			'users' => [],
		], $board->jsonSerialize());
	}
}