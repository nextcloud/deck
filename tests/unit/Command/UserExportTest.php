<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Command;

use OCA\Deck\Db\Board;
use OCA\Deck\Service\BoardExportOptions;
use OCA\Deck\Service\BoardExportService;
use OCA\Deck\Service\BoardService;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserExportTest extends \Test\TestCase {
	/** @var IAppManager|MockObject */
	protected $appManager;
	/** @var BoardService|MockObject */
	protected $boardService;
	/** @var BoardExportService|MockObject */
	protected $boardExportService;

	private UserExport $userExport;

	public function setUp(): void {
		parent::setUp();
		$this->appManager = $this->createMock(IAppManager::class);
		$this->boardService = $this->createMock(BoardService::class);
		$this->boardExportService = $this->createMock(BoardExportService::class);
		$this->userExport = new UserExport($this->appManager, $this->boardService, $this->boardExportService);
	}

	private function board(int $id): Board {
		$board = new Board();
		$board->setId($id);
		$board->setTitle('Board ' . $id);
		return $board;
	}

	private function input(bool $legacyFormat = false, bool $noAttachments = false): InputInterface&MockObject {
		$input = $this->createMock(InputInterface::class);
		$input->method('getArgument')->with('user-id')->willReturn('admin');
		$input->method('getOption')->willReturnCallback(static fn (string $name) => match ($name) {
			'legacy-format' => $legacyFormat,
			'no-attachments' => $noAttachments,
		});
		return $input;
	}

	public function testExecuteExportsAllBoardsOfTheUser() {
		$boards = [$this->board(1), $this->board(2)];

		$this->boardService->expects($this->once())->method('setUserId')->with('admin');
		$this->boardExportService->expects($this->once())->method('setUserId')->with('admin');
		$this->boardService->expects($this->once())
			->method('findAll')
			->willReturn($boards);
		$this->boardExportService->expects($this->once())
			->method('exportBoards')
			->with($boards, $this->callback(static fn (BoardExportOptions $options) => $options->includeAttachments === true))
			->willReturn([1 => ['id' => 1], 2 => ['id' => 2]]);
		$this->appManager->method('getAppVersion')->with('deck')->willReturn('1.19.0');

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('writeln')
			->with($this->callback(static function (string $json): bool {
				$decoded = json_decode($json, true);
				// json_decode turns the board ids back into integer array keys
				return $decoded['version'] === '1.19.0'
					&& array_keys($decoded['boards']) === [1, 2];
			}));

		self::assertEquals(0, $this->invokePrivate($this->userExport, 'execute', [$this->input(), $output]));
	}

	public function testExecuteExportsNothingForAUserWithoutBoards() {
		$this->boardService->method('findAll')->willReturn([]);
		$this->boardExportService->expects($this->once())
			->method('exportBoards')
			->with([], $this->anything())
			->willReturn([]);
		$this->appManager->method('getAppVersion')->willReturn('1.19.0');

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('writeln')
			->with($this->callback(static function (string $json): bool {
				$decoded = json_decode($json, true);
				return $decoded['boards'] === [] && $decoded['version'] === '1.19.0';
			}));

		self::assertEquals(0, $this->invokePrivate($this->userExport, 'execute', [$this->input(), $output]));
	}

	public function testExecuteWithLegacyFormatOmitsTheEnvelope() {
		$this->boardService->method('findAll')->willReturn([$this->board(1)]);
		$this->boardExportService->method('exportBoards')->willReturn([1 => ['id' => 1]]);

		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('writeln')
			->with($this->callback(static function (string $json): bool {
				$decoded = json_decode($json, true);
				return !isset($decoded['version']) && $decoded['1']['id'] === 1;
			}));

		self::assertEquals(0, $this->invokePrivate($this->userExport, 'execute', [$this->input(legacyFormat: true), $output]));
	}

	public function testExecuteCanSkipAttachments() {
		$this->boardService->method('findAll')->willReturn([$this->board(1)]);
		$this->boardExportService->expects($this->once())
			->method('exportBoards')
			->with(
				$this->anything(),
				$this->callback(static function (BoardExportOptions $options): bool {
					// only the attachment contents are dropped, the rest of the board stays complete
					return $options->includeAttachments === false
						&& $options->includeArchivedCards === true
						&& $options->includeComments === true;
				}),
			)
			->willReturn([]);

		$output = $this->createMock(OutputInterface::class);

		self::assertEquals(0, $this->invokePrivate($this->userExport, 'execute', [$this->input(noAttachments: true), $output]));
	}
}
