<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCA\Circles\Model\Circle;
use PHPUnit\Framework\TestCase;

class TeamOwnerTest extends TestCase {
	public function testJsonSerializeUsesTeamDisplayNameAndKeepsUserId(): void {
		if (!class_exists(Circle::class)) {
			$this->markTestSkipped('Circles app is not available');
		}

		$circle = $this->createMock(Circle::class);
		$circle->method('getDisplayName')->willReturn('toasted');
		$circle->method('getSingleId')->willReturn('team-single-id');

		$owner = new TeamOwner('user1', $circle);

		$this->assertEquals([
			'primaryKey' => 'user1',
			'uid' => 'user1',
			'displayname' => 'team board',
			'type' => Acl::PERMISSION_TYPE_CIRCLE,
			'teamId' => 'team-single-id',
		], $owner->jsonSerialize());
	}
}
