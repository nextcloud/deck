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
use PHPUnit\Framework\TestCase;

class SettingTest extends TestCase {

	/** @var IL10N */
	private $l10n;
	/** @var Setting */
	private $setting;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->expects($this->any())->method('t')->will($this->returnCallback(function ($s) {
			return $s;
		}));
		$this->setting = new Setting($this->l10n);
	}

	public function testGetIdentifier() {
		$this->assertEquals('deck', $this->setting->getIdentifier());
	}

	public function testGetName() {
		$this->assertEquals('Changes in the <strong>Deck app</strong>', $this->setting->getName());
	}

	public function testGetPriority() {
		$this->assertEquals(90, $this->setting->getPriority());
	}

	public function testCanChangeStream() {
		$this->assertTrue($this->setting->canChangeStream());
	}

	public function testIsDefaultEnabledStream() {
		$this->assertTrue($this->setting->isDefaultEnabledStream());
	}

	public function testCanChangeMail() {
		$this->assertTrue($this->setting->canChangeMail());
	}

	public function testIsDefaultEnabledMail() {
		$this->assertFalse($this->setting->isDefaultEnabledMail());
	}
}
