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

namespace OCA\Deck\Service;

use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use Test\TestCase;

class LabelServiceTest extends TestCase {

	/** @var  LabelMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $labelMapper;
	/** @var  PermissionService|\PHPUnit\Framework\MockObject\MockObject */
	private $permissionService;
	/** @var  LabelService */
	private $labelService;
	/** @var BoardService|\PHPUnit\Framework\MockObject\MockObject */
	private $boardService;
	/** @var ChangeHelper|\PHPUnit\Framework\MockObject\MockObject */
	private $changeHelper;

	public function setUp(): void {
		parent::setUp();
		$this->labelMapper = $this->getMockBuilder(LabelMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->permissionService = $this->getMockBuilder(PermissionService::class)
			->disableOriginalConstructor()->getMock();
		$this->boardService = $this->createMock(BoardService::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->labelService = new LabelService(
			$this->labelMapper,
			$this->permissionService,
			$this->boardService,
			$this->changeHelper
		);
	}

	public function testFind() {
		$this->labelMapper->expects($this->once())->method('find')->willReturn(true);
		$this->assertTrue($this->labelService->find(123));
	}

	public function testCreate() {
		$label = new Label();
		$label->setTitle('Label title');
		$label->setBoardId(123);
		$label->setColor('00ff00');
		$this->labelMapper->expects($this->once())
			->method('insert')
			->willReturn($label);
		$b = $this->labelService->create('Label title', '00ff00', 123);

		$this->assertEquals($b->getTitle(), 'Label title');
		$this->assertEquals($b->getBoardId(), 123);
		$this->assertEquals($b->getColor(), '00ff00');
	}


	public function testUpdate() {
		$label = new Label();
		$label->setTitle('Title');
		$label->setBoardId(123);
		$label->setColor('00ff00');
		$this->labelMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($label);
		$this->labelMapper->expects($this->once())
			->method('update')
			->with($label)
			->willReturn($label);
		$b = $this->labelService->update(1, 'NewTitle', 'ffffff');

		$this->assertEquals($b->getTitle(), 'NewTitle');
		$this->assertEquals($b->getBoardId(), 123);
		$this->assertEquals($b->getColor(), 'ffffff');
	}

	public function testDelete() {
		$label = new Label();
		$label->setId(1);
		$this->labelMapper->expects($this->once())
			->method('find')
			->willReturn($label);
		$this->labelMapper->expects($this->once())
			->method('delete')
			->willReturn($label);
		$this->assertEquals($label, $this->labelService->delete(1));
	}
}
