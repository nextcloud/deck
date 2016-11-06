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

use OC\L10N\L10N;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\LabelMapper;
use OCP\IGroupManager;
use OCP\ILogger;

class BoardServiceTest extends \PHPUnit_Framework_TestCase {

	private $service;
	private $logger;
	private $l10n;
	private $labelMapper;
	private $aclMapper;
	private $boardMapper;
	private $groupManager;

	private $userId = 'admin';

	public function setUp() {
		$this->logger = $this->request = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10n = $this->request = $this->getMockBuilder(L10N::class)
			->disableOriginalConstructor()
			->getMock();
		$this->aclMapper = $this->getMockBuilder(AclMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->boardMapper = $this->getMockBuilder(BoardMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->labelMapper = $this->getMockBuilder(LabelMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->groupManager = $this->getMockBuilder(IGroupManager::class)
			->disableOriginalConstructor()->getMock();

		$this->service = new BoardService(
			$this->boardMapper,
			$this->logger,
			$this->l10n,
			$this->labelMapper,
			$this->aclMapper,
			$this->groupManager
		);
	}

	public function testFindAll() {
		$this->boardMapper->expects($this->once())
			->method('findAllByUser')
			->with('admin')
			->willReturn([1,2,3,6,7]);
		$this->boardMapper->expects($this->once())
			->method('findAllByGroups')
			->with('admin', ['a', 'b', 'c'])
			->willReturn([4,5,6,7,8]);
		$userinfo = [
			'user' => 'admin',
			'groups' => ['a', 'b', 'c']
		];
		$result = $this->service->findAll($userinfo);
		sort($result);
		$this->assertEquals([1,2,3,4,5,6,7,8], $result);
	}

	public function testFind() {
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn(1);
		$this->assertEquals(1, $this->service->find(123));
	}

}