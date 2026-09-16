<?php

/**
 * @copyright Copyright (c) 2026 Julius Härtl <jus@bitgrid.net>
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

use OCA\Deck\Exceptions\PreconditionFailedException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ChangeHelperTest extends TestCase {

	private $db;
	private $cache;
	private $cacheFactory;
	private $request;
	private $changeHelper;

	protected function setUp(): void {
		parent::setUp();
		$this->db = $this->createMock(IDBConnection::class);
		$this->cache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($this->cache);
		$this->request = $this->createMock(IRequest::class);
		$this->changeHelper = new ChangeHelper(
			$this->db,
			$this->cacheFactory,
			$this->request,
			'user1'
		);
	}

	public function testCheckIfMatchNoHeader() {
		$this->request->method('getHeader')->with('If-Match')->willReturn('');
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_BOARD, 1);
		$this->addToAssertionCount(1);
	}

	public function testCheckIfMatchSuccess() {
		$this->request->method('getHeader')->with('If-Match')->willReturn('my-etag');
		$this->cache->method('get')->with('boardChanged-1')->willReturn('my-etag');
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_BOARD, 1);
		$this->addToAssertionCount(1);
	}

	public function testCheckIfMatchQuotedSuccess() {
		$this->request->method('getHeader')->with('If-Match')->willReturn('"my-etag"');
		$this->cache->method('get')->with('boardChanged-1')->willReturn('my-etag');
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_BOARD, 1);
		$this->addToAssertionCount(1);
	}

	public function testCheckIfMatchWildcardSuccess() {
		$this->request->method('getHeader')->with('If-Match')->willReturn('*');
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_BOARD, 1);
		$this->addToAssertionCount(1);
	}

	public function testCheckIfMatchMismatch() {
		$this->request->method('getHeader')->with('If-Match')->willReturn('wrong-etag');
		$this->cache->method('get')->with('boardChanged-1')->willReturn('correct-etag');
		$this->expectException(PreconditionFailedException::class);
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_BOARD, 1);
	}

	public function testCheckIfMatchFallbackSuccess() {
		$this->request->method('getHeader')->with('If-Match')->willReturn('fallback-etag');
		$this->cache->method('get')->with('boardChanged-1')->willReturn('null');
		$this->changeHelper->checkIfMatch(ChangeHelper::TYPE_BOARD, 1, 'fallback-etag');
		$this->addToAssertionCount(1);
	}
}
