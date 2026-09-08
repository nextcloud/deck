<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;

class ShareFileAttachmentExportServiceTest extends \Test\TestCase {
	/** @var IDBConnection|MockObject */
	private $dbConnection;
	/** @var IRootFolder|MockObject */
	private $rootFolder;
	/** @var IQueryBuilder|MockObject */
	private $queryBuilder;
	/** @var IResult|MockObject */
	private $result;
	private ShareFileAttachmentExportService $service;

	public function setUp(): void {
		parent::setUp();

		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$this->result = $this->createMock(IResult::class);
		$this->queryBuilder = $this->createMock(IQueryBuilder::class);
		$this->queryBuilder->method('select')->willReturnSelf();
		$this->queryBuilder->method('from')->willReturnSelf();
		$this->queryBuilder->method('where')->willReturnSelf();
		$this->queryBuilder->method('andWhere')->willReturnSelf();
		$this->queryBuilder->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$this->queryBuilder->method('createNamedParameter')->willReturn('param');
		$this->queryBuilder->method('executeQuery')->willReturn($this->result);
		$this->dbConnection->method('getQueryBuilder')->willReturn($this->queryBuilder);

		$this->service = new ShareFileAttachmentExportService(
			$this->dbConnection,
			$this->rootFolder,
		);
	}

	/**
	 * A row of `oc_share` as the deck share provider writes it: the card id is
	 * stored in `share_with`.
	 */
	private function shareRow(int $cardId, int $fileId, array $overrides = []): array {
		return array_merge([
			'id' => $fileId,
			'uid_owner' => 'owner',
			'uid_initiator' => 'initiator',
			'file_source' => $fileId,
			'stime' => 1689667796,
			'share_with' => (string)$cardId,
		], $overrides);
	}

	private function file(string $name, string $content, int $mtime = 1689667800): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		$file->method('getContent')->willReturn($content);
		$file->method('getMTime')->willReturn($mtime);
		return $file;
	}

	public function testNoCardsIsNotQueried(): void {
		$this->dbConnection->expects($this->never())->method('getQueryBuilder');

		self::assertSame([], $this->service->exportAttachmentsForCards([], 'admin'));
	}

	public function testAttachmentsAreGroupedByCard(): void {
		$this->result->method('fetchAllAssociative')->willReturn([
			$this->shareRow(100, 1),
			$this->shareRow(101, 2),
			$this->shareRow(100, 3),
		]);
		$this->rootFolder->method('getById')->willReturnCallback(fn (int $id) => [$this->file('file' . $id . '.txt', 'content' . $id)]);

		$attachments = $this->service->exportAttachmentsForCards([100, 101], 'admin');

		self::assertSame([100, 101], array_keys($attachments));
		self::assertSame(['file1.txt', 'file3.txt'], array_column($attachments[100], 'data'));
		self::assertSame(['file2.txt'], array_column($attachments[101], 'data'));
	}

	public function testAttachmentIsSerializedWithItsContent(): void {
		$this->result->method('fetchAllAssociative')->willReturn([$this->shareRow(100, 7)]);
		$this->rootFolder->method('getById')->with(7)->willReturn([$this->file('report.pdf', 'binary')]);

		$attachment = $this->service->exportAttachmentsForCards([100], 'admin')[100][0];

		self::assertSame([
			'type' => 'file',
			'data' => 'report.pdf',
			'createdBy' => 'initiator',
			'createdAt' => 1689667796,
			'lastModified' => 1689667800,
			'contentBase64' => base64_encode('binary'),
		], $attachment);
	}

	public function testCreatedByFallsBackToTheOwnerAndThenToTheExportingUser(): void {
		$this->result->method('fetchAllAssociative')->willReturn([
			$this->shareRow(100, 1, ['uid_initiator' => null]),
			$this->shareRow(101, 2, ['uid_initiator' => null, 'uid_owner' => null]),
		]);
		$this->rootFolder->method('getById')->willReturnCallback(fn (int $id) => [$this->file('file' . $id, 'x')]);

		$attachments = $this->service->exportAttachmentsForCards([100, 101], 'fallback');

		self::assertSame('owner', $attachments[100][0]['createdBy']);
		self::assertSame('fallback', $attachments[101][0]['createdBy']);
	}

	public function testSharesWithoutAReadableNodeAreSkipped(): void {
		$this->result->method('fetchAllAssociative')->willReturn([
			$this->shareRow(100, 1),
			$this->shareRow(100, 2),
		]);
		$this->rootFolder->method('getById')->willReturnCallback(function (int $id) {
			// the file behind the share was deleted in the meantime
			return $id === 1 ? [] : [$this->file('kept.txt', 'x')];
		});

		$attachments = $this->service->exportAttachmentsForCards([100], 'admin');

		self::assertSame(['kept.txt'], array_column($attachments[100], 'data'));
	}

	public function testUnreadableFilesDoNotAbortTheExport(): void {
		$broken = $this->createMock(File::class);
		$broken->method('getName')->willReturn('broken.txt');
		$broken->method('getContent')->willThrowException(new NotFoundException('gone'));

		$this->result->method('fetchAllAssociative')->willReturn([
			$this->shareRow(100, 1),
			$this->shareRow(100, 2),
		]);
		$this->rootFolder->method('getById')->willReturnCallback(function (int $id) use ($broken) {
			return $id === 1 ? [$broken] : [$this->file('kept.txt', 'x')];
		});

		$attachments = $this->service->exportAttachmentsForCards([100], 'admin');

		self::assertSame(['kept.txt'], array_column($attachments[100], 'data'));
	}

	public function testCardsWithoutSharesAreNotInTheResult(): void {
		$this->result->method('fetchAllAssociative')->willReturn([]);

		self::assertSame([], $this->service->exportAttachmentsForCards([100, 101], 'admin'));
	}

	public function testExportCardAttachmentsReturnsTheListOfASingleCard(): void {
		$this->result->method('fetchAllAssociative')->willReturn([$this->shareRow(100, 1)]);
		$this->rootFolder->method('getById')->willReturn([$this->file('single.txt', 'x')]);

		$attachments = $this->service->exportCardAttachments(100, 'admin');

		self::assertSame(['single.txt'], array_column($attachments, 'data'));
	}

	public function testExportCardAttachmentsReturnsAnEmptyListForACardWithoutShares(): void {
		$this->result->method('fetchAllAssociative')->willReturn([]);

		self::assertSame([], $this->service->exportCardAttachments(100, 'admin'));
	}
}
