<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\ConfigService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ConfigControllerTest extends TestCase {
	private ConfigService&MockObject $configService;
	private PermissionService&MockObject $permissionService;
	private BoardMapper&MockObject $boardMapper;
	private BoardService&MockObject $boardService;
	private ConfigController $controller;

	public function setUp(): void {
		parent::setUp();

		$this->configService = $this->createMock(ConfigService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->controller = new ConfigController(
			'deck',
			$this->createMock(IRequest::class),
			$this->configService,
			$this->permissionService,
			$this->boardMapper,
			$this->boardService,
		);
	}

	public static function dataSwimlaneKeys(): array {
		return [
			'mode' => ['board:5:swimlaneMode', 'swimlaneMode', 'labels'],
			'label order' => ['board:5:swimlaneLabelOrder', 'swimlaneLabelOrder', '[2,1]'],
			'user order' => ['board:5:swimlaneUserOrder', 'swimlaneUserOrder', '["0:bob"]'],
			'leading zero' => ['board:05:swimlaneMode', 'swimlaneMode', 'assignees'],
		];
	}

	/**
	 * @dataProvider dataSwimlaneKeys
	 */
	public function testSwimlaneSettingGoesToBoardServiceAfterEditCheck(string $key, string $setting, string $value): void {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 5, Acl::PERMISSION_EDIT);
		$this->boardService->expects($this->once())
			->method('setSwimlaneSetting')
			->with(5, $setting, $value)
			// The service stores a canonical form, the answer has to be that one.
			->willReturn($value . ' stored');
		$this->configService->expects($this->never())->method('set');

		$response = $this->controller->setValue($key, $value);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame($value . ' stored', $response->getData());
	}

	public function testNoEditPermissionWritesNothing(): void {
		$this->permissionService->method('checkPermission')
			->willThrowException(new NoPermissionException('denied'));
		$this->boardService->expects($this->never())->method('setSwimlaneSetting');
		$this->configService->expects($this->never())->method('set');

		$this->expectException(NoPermissionException::class);
		$this->controller->setValue('board:5:swimlaneMode', 'labels');
	}

	public function testOtherBoardSettingStaysUserConfig(): void {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->boardMapper, 5, Acl::PERMISSION_EDIT);
		$this->boardService->expects($this->never())->method('setSwimlaneSetting');
		$this->configService->expects($this->once())
			->method('set')
			->with('board:5:notify-due', 'all')
			->willReturn('all');

		$this->assertSame('all', $this->controller->setValue('board:5:notify-due', 'all')->getData());
	}

	public function testNonNumericBoardIdIsNotRoutedToBoardService(): void {
		$this->permissionService->expects($this->never())->method('checkPermission');
		$this->boardService->expects($this->never())->method('setSwimlaneSetting');
		$this->configService->expects($this->once())
			->method('set')
			->with('board:abc:swimlaneMode', 'labels')
			->willReturn('labels');

		$this->controller->setValue('board:abc:swimlaneMode', 'labels');
	}

	public function testUnknownKeyIsNotFound(): void {
		$this->configService->method('set')->willReturn(null);

		$this->assertInstanceOf(NotFoundResponse::class, $this->controller->setValue('unknown', 'x'));
	}
}
