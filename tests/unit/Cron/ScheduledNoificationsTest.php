<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Cron;

use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Notification\NotificationHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ScheduledNoificationsTest extends TestCase {

	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var CardMapper|MockObject */
	protected $cardMapper;
	/** @var NotificationHelper|MockObject */
	protected $notificationHelper;
	/** @var ILogger|MockObject */
	protected $logger;
	/** @var ScheduledNotifications */
	protected $scheduledNotifications;

	public function setUp(): void {
		parent::setUp();
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->scheduledNotifications = new ScheduledNotifications($this->timeFactory, $this->cardMapper, $this->notificationHelper, $this->logger);
	}

	public function testScheduledCron() {
		$c1 = new Card();
		$c2 = new Card();
		$cards = [$c1, $c2];
		$this->cardMapper->expects($this->once())
			->method('findOverdue')
			->willReturn($cards);
		$this->notificationHelper->expects($this->exactly(2))
			->method('sendCardDuedate')
			->with($c1);
		$this->scheduledNotifications->run(null);
	}
}
