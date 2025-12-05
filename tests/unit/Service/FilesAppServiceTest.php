<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Attachment;
use OCP\Files\IFilenameValidator;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FilesAppServiceTest extends TestCase {

	private IRequest&MockObject $request;
	private IFilenameValidator&MockObject $filenameValidator;
	private IL10N&MockObject $l10n;
	private \OCP\Files\IRootFolder&MockObject $rootFolder;
	private ConfigService&MockObject $configService;
	private \OCP\Share\IManager&MockObject $shareManager;
	private FilesAppService $filesAppService;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->filenameValidator = $this->createMock(IFilenameValidator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(fn ($string, $args = []) => vsprintf($string, $args));

		$this->rootFolder = $this->createMock(\OCP\Files\IRootFolder::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->shareManager = $this->createMock(\OCP\Share\IManager::class);

		$this->filesAppService = new FilesAppService(
			$this->request,
			$this->l10n,
			$this->rootFolder,
			$this->shareManager,
			$this->configService,
			$this->createMock(\OCA\Deck\Sharing\DeckShareProvider::class),
			$this->createMock(\OCP\IPreview::class),
			$this->createMock(\OCP\Files\IMimeTypeDetector::class),
			$this->createMock(PermissionService::class),
			$this->createMock(\OCA\Deck\Db\CardMapper::class),
			$this->createMock(\Psr\Log\LoggerInterface::class),
			$this->createMock(\OCP\IDBConnection::class),
			$this->filenameValidator,
			'admin'
		);
	}

	public function testCreateWithInvalidFilename() {
		$this->expectException(BadRequestException::class);
		
		$attachment = new Attachment();
		$attachment->setCardId(123);

		$this->request->expects($this->once())
			->method('getUploadedFile')
			->with('file')
			->willReturn([
				'name' => 'test:file.txt',
				'tmp_name' => __FILE__,
				'error' => UPLOAD_ERR_OK,
			]);

		$this->filenameValidator->expects($this->once())
			->method('validateFilename')
			->with('test:file.txt')
			->willThrowException(new \OCP\Files\InvalidPathException('":\" is not allowed inside a file or folder name.'));

		$this->filesAppService->create($attachment);
	}

	public function testCreateWithValidFilename() {
		$attachment = new Attachment();
		$attachment->setCardId(123);

		$this->request->expects($this->once())
			->method('getUploadedFile')
			->with('file')
			->willReturn([
				'name' => 'valid-file.txt',
				'tmp_name' => __FILE__,
				'error' => UPLOAD_ERR_OK,
			]);

		$this->filenameValidator->expects($this->once())
			->method('validateFilename')
			->with('valid-file.txt');

		$userFolder = $this->createMock(\OCP\Files\Folder::class);
		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->willReturn($userFolder);

		$this->configService->expects($this->any())
			->method('getAttachmentFolder')
			->willReturn('deck');

		$folder = $this->createMock(\OCP\Files\Folder::class);
		$userFolder->expects($this->any())
			->method('get')
			->willReturn($folder);

		$folder->expects($this->any())
			->method('isShared')
			->willReturn(false);

		$folder->expects($this->any())
			->method('getNonExistingName')
			->willReturnArgument(0);

		$file = $this->createMock(\OCP\Files\File::class);
		$folder->expects($this->any())
			->method('newFile')
			->willReturn($file);

		$file->expects($this->any())
			->method('putContent');

		$file->expects($this->any())
			->method('getName')
			->willReturn('valid-file.txt');

		$share = $this->createMock(\OCP\Share\IShare::class);
		$share->expects($this->any())
			->method('getId')
			->willReturn('123');
		$share->expects($this->any())
			->method('setNode')
			->willReturnSelf();
		$share->expects($this->any())
			->method('setShareType')
			->willReturnSelf();
		$share->expects($this->any())
			->method('setSharedWith')
			->willReturnSelf();
		$share->expects($this->any())
			->method('setPermissions')
			->willReturnSelf();
		$share->expects($this->any())
			->method('setSharedBy')
			->willReturnSelf();

		$this->shareManager->expects($this->any())
			->method('newShare')
			->willReturn($share);
		$this->shareManager->expects($this->any())
			->method('createShare')
			->willReturn($share);

		try {
			$this->filesAppService->create($attachment);
		} catch (BadRequestException $e) {
			$this->fail('Validation should pass for valid filename, but BadRequestException was thrown: ' . $e->getMessage());
		} catch (\Exception $e) {
			// Other exceptions are expected since not everything is mocked.
		}
	}
}
