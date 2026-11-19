<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\BoardView;
use OCA\Deck\Db\BoardViewMapper;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class BoardViewServiceTest extends TestCase {
	/** @var BoardViewMapper|MockObject */
	private $boardViewMapper;
	/** @var BoardService|MockObject */
	private $boardService;
	/** @var ConfigService|MockObject */
	private $configService;
	private BoardViewService $boardViewService;

	public function setUp(): void {
		parent::setUp();
		$this->boardViewMapper = $this->createMock(BoardViewMapper::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->boardViewService = new BoardViewService(
			$this->boardViewMapper,
			$this->boardService,
			$this->configService,
			'user123',
		);
	}

	public function testFindAll() {
		$view = new BoardView();
		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('findAll')->with(123, 'user123')->willReturn([$view]);
		$this->assertEquals([$view], $this->boardViewService->findAll(123));
	}

	public function testCreate() {
		$filters = ['tags' => [1], 'users' => [], 'due' => 'overdue', 'unassigned' => false, 'completed' => 'open'];

		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('findAll')->with(123, 'user123')->willReturn([]);
		$this->boardViewMapper->expects($this->once())->method('insert')->willReturnCallback(function (BoardView $view) {
			return $view;
		});

		$view = $this->boardViewService->create(123, 'My view', $filters);
		$this->assertEquals(123, $view->getBoardId());
		$this->assertEquals('My view', $view->getName());
		$this->assertEquals('user123', $view->getOwner());
		$this->assertEquals(json_encode($filters), $view->getFilters());
		$this->assertNotNull($view->getCreatedAt());
		$this->assertNotNull($view->getLastModifiedAt());
	}

	public function testCreateTrimsName() {
		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('findAll')->with(123, 'user123')->willReturn([]);
		$this->boardViewMapper->expects($this->once())->method('insert')->willReturnCallback(function (BoardView $view) {
			return $view;
		});

		$view = $this->boardViewService->create(123, '  My view  ', []);
		$this->assertEquals('My view', $view->getName());
	}

	public function testCreateEmptyName() {
		$this->expectException(BadRequestException::class);
		$this->boardViewService->create(123, '   ', []);
	}

	public function testCreateNameTooLong() {
		$this->expectException(BadRequestException::class);
		$this->boardViewService->create(123, str_repeat('a', 256), []);
	}

	public function testCreateTooManyViews() {
		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('findAll')->with(123, 'user123')->willReturn(array_fill(0, 50, new BoardView()));

		$this->expectException(BadRequestException::class);
		$this->boardViewService->create(123, 'My view', []);
	}

	public function testCreateFiltersTooLarge() {
		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('findAll')->with(123, 'user123')->willReturn([]);

		$this->expectException(BadRequestException::class);
		$this->boardViewService->create(123, 'My view', ['description' => str_repeat('a', 60000)]);
	}

	public function testUpdate() {
		$view = new BoardView();
		$view->setId(1);
		$view->setBoardId(123);
		$view->setName('Old name');
		$view->setFilters(json_encode(['tags' => []]));

		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('find')->with(1, 'user123')->willReturn($view);
		$this->boardViewMapper->expects($this->once())->method('update')->willReturnCallback(function (BoardView $view) {
			return $view;
		});

		$updated = $this->boardViewService->update(123, 1, 'New name', ['tags' => [2]]);
		$this->assertEquals('New name', $updated->getName());
		$this->assertEquals(json_encode(['tags' => [2]]), $updated->getFilters());
	}

	public function testUpdateEmptyNameKeepsOldName() {
		$view = new BoardView();
		$view->setId(1);
		$view->setBoardId(123);
		$view->setName('Old name');
		$view->setFilters(json_encode(['tags' => []]));

		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('find')->with(1, 'user123')->willReturn($view);
		$this->boardViewMapper->expects($this->once())->method('update')->willReturnCallback(function (BoardView $view) {
			return $view;
		});

		$updated = $this->boardViewService->update(123, 1, '', ['tags' => [2]]);
		$this->assertEquals('Old name', $updated->getName());
	}

	public function testUpdateViewOfOtherBoard() {
		$view = new BoardView();
		$view->setId(1);
		$view->setBoardId(456);

		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('find')->with(1, 'user123')->willReturn($view);

		$this->expectException(BadRequestException::class);
		$this->boardViewService->update(123, 1, 'New name', []);
	}

	public function testDelete() {
		$view = new BoardView();
		$view->setId(1);
		$view->setBoardId(123);

		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('find')->with(1, 'user123')->willReturn($view);
		$this->boardViewMapper->expects($this->once())->method('delete')->with($view);
		$this->configService->expects($this->once())->method('getDefaultBoardView')->willReturn(null);

		$this->boardViewService->delete(123, 1);
	}

	public function testDeleteClearsDefaultView() {
		$view = new BoardView();
		$view->setId(1);
		$view->setBoardId(123);

		$this->boardService->expects($this->once())->method('find')->with(123, false);
		$this->boardViewMapper->expects($this->once())->method('find')->with(1, 'user123')->willReturn($view);
		$this->boardViewMapper->expects($this->once())->method('delete')->with($view);
		$this->configService->expects($this->once())->method('getDefaultBoardView')->willReturn(1);
		$this->configService->expects($this->once())->method('set')->with('defaultBoardView', null);

		$this->boardViewService->delete(123, 1);
	}
}
