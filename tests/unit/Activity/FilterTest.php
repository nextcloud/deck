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

namespace OCA\Deck\Activity;

use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class FilterTest extends TestCase {

	/** @var Filter */
	private $filter;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->filter = new Filter($this->l10n, $this->urlGenerator);
	}

	public function testGetIdentifier() {
		$this->assertEquals('deck', $this->filter->getIdentifier());
	}

	public function testGetName() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Deck')
			->willReturn('Deck');
		$this->assertEquals('Deck', $this->filter->getName());
	}

	public function testGetPriority() {
		$this->assertEquals(90, $this->filter->getPriority());
	}

	public function testGetIcon() {
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('deck', 'deck-dark.svg')
			->willReturn('http://localhost/apps/deck/img/deck-dark.svg');
		$this->assertEquals('http://localhost/apps/deck/img/deck-dark.svg', $this->filter->getIcon());
	}

	public function testFilterTypes() {
		$data = ['deck_board', 'deck_card'];
		$this->assertEquals(array_merge($data, ['deck_comment']), $this->filter->filterTypes($data));
	}

	public function testAllowedApps() {
		$this->assertEquals(['deck'], $this->filter->allowedApps());
	}
}
