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

namespace OCA\Deck\Service;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FileServiceTest extends TestCase {

	/** @var IL10N|MockObject */
	private $l10n;
	/** @var IAppData|MockObject */
	private $appData;
	/** @var IRequest|MockObject */
	private $request;
	/** @var ILogger|MockObject */
	private $logger;
	/** @var FileService */
	private $fileService;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IConfig */
	private $config;
	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var IMimeTypeDetector|MockObject */
	private $mimeTypeDetector;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->config = $this->createMock(IConfig::class);
		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->fileService = new FileService($this->l10n, $this->appData, $this->request, $this->logger, $this->rootFolder, $this->config, $this->attachmentMapper, $this->mimeTypeDetector);
	}

	public function mockGetFolder($cardId) {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('file-card-' . $cardId)
			->willReturn($folder);
		return $folder;
	}
	public function mockGetFolderFailure($cardId) {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('file-card-' . $cardId)
			->will($this->throwException(new \OCP\Files\NotFoundException()));
		$this->appData->expects($this->once())
			->method('newFolder')
			->with('file-card-' . $cardId)
			->willReturn($folder);
		return $folder;
	}

	private function getAttachment() {
		$attachment = new Attachment();
		$attachment->setId(1);
		$attachment->setCardId(123);
		$attachment->setData('Filename.md');
		return $attachment;
	}

	private function mockGetUploadedFileEmpty() {
		$this->request->expects($this->once())
			->method('getUploadedFile')
			->willReturn([]);
	}
	private function mockGetUploadedFileError($error) {
		$this->request->expects($this->once())
			->method('getUploadedFile')
			->willReturn(['error' => $error]);
	}
	private function mockGetUploadedFile() {
		$this->request->expects($this->once())
			->method('getUploadedFile')
			->willReturn([
				'name' => 'file.jpg',
				'tmp_name' => __FILE__,
			]);
	}

	public function testExtendDataNotFound() {
		$attachment = $this->getAttachment();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())->method('getFile')->will($this->throwException(new \OCP\Files\NotFoundException()));
		$this->assertEquals($attachment, $this->fileService->extendData($attachment));
	}

	public function testExtendDataNotPermitted() {
		$attachment = $this->getAttachment();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())->method('getFile')->will($this->throwException(new \OCP\Files\NotPermittedException()));
		$this->assertEquals($attachment, $this->fileService->extendData($attachment));
	}

	public function testExtendData() {
		$attachment = $this->getAttachment();
		$expected = $this->getAttachment();
		$expected->setExtendedData([
			'filesize' => 100,
			'mimetype' => 'image/jpeg',
			'info' => pathinfo(__FILE__)
		]);

		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())->method('getSize')->willReturn(100);
		$file->expects($this->once())->method('getMimeType')->willReturn('image/jpeg');
		$file->expects($this->once())->method('getName')->willReturn(__FILE__);

		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())->method('getFile')->willReturn($file);
		$this->assertEquals($expected, $this->fileService->extendData($attachment));
	}

	public function testCreateEmpty() {
		$this->expectException(\Exception::class);
		$attachment = $this->getAttachment();
		$this->l10n->expects($this->any())
			->method('t')
			->willReturn('Error');
		$this->mockGetUploadedFileEmpty();
		$this->fileService->create($attachment);
	}

	public function testCreateError() {
		$this->expectException(\Exception::class);
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFileError(UPLOAD_ERR_INI_SIZE);
		$this->l10n->expects($this->any())
			->method('t')
			->willReturn('Error');
		$this->fileService->create($attachment);
	}

	public function testCreate() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())
			->method('fileExists')
			->willReturn(false);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent');
		// FIXME: test fopen call properly
		$folder->expects($this->once())
			->method('newFile')
			->willReturn($file);

		$this->fileService->create($attachment);
	}

	public function testCreateNoFolder() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolderFailure(123);
		$folder->expects($this->once())
			->method('fileExists')
			->willReturn(false);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent');
		// FIXME: test fopen call properly
		$folder->expects($this->once())
			->method('newFile')
			->willReturn($file);

		$this->fileService->create($attachment);
	}

	public function testCreateExists() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('File already exists.');

		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolder(123);
		$folder->expects($this->once())
			->method('fileExists')
			->willReturn(true);
		$this->fileService->create($attachment);
	}

	public function testUpdate() {
		$attachment = $this->getAttachment();
		$this->mockGetUploadedFile();
		$folder = $this->mockGetFolder(123);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('putContent');
		// FIXME: test fopen call properly
		$folder->expects($this->once())
			->method('getFile')
			->willReturn($file);

		$this->fileService->update($attachment);
	}

	public function testDelete() {
		$attachment = $this->getAttachment();
		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->mockGetFolder('123');
		$folder->expects($this->once())
			->method('getFile')
			->willReturn($file);
		$file->expects($this->once())
			->method('delete');
		$this->fileService->delete($attachment);
	}

	public function testDisplay() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->willReturn('123');
		$appDataFolder = $this->createMock(Folder::class);
		$deckAppDataFolder = $this->createMock(Folder::class);
		$cardFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())->method('get')->willReturn($appDataFolder);
		$appDataFolder->expects($this->once())->method('get')->willReturn($deckAppDataFolder);
		$deckAppDataFolder->expects($this->once())->method('get')->willReturn($cardFolder);
		$attachment = $this->getAttachment();
		$file = $this->createMock(\OCP\Files\File::class);
		$cardFolder->expects($this->once())->method('get')->willReturn($file);
		$file->expects($this->any())
			->method('getMimeType')
			->willReturn('image/jpeg');
		$file->expects($this->any())
			->method('getName')
			->willReturn('file1');
		$file->expects($this->any())
			->method('fopen')
			->willReturn('fileresource');
		$this->mimeTypeDetector->expects($this->once())
			->method('getSecureMimeType')
			->willReturn('image/jpeg');
		$actual = $this->fileService->display($attachment);
		$expected = new StreamResponse('fileresource');
		$expected->addHeader('Content-Type', 'image/jpeg');
		$expected->addHeader('Content-Disposition', 'attachment; filename="' . rawurldecode($file->getName()) . '"');
		$this->assertEquals($expected, $actual);
	}

	public function testAllowUndo() {
		$this->assertTrue($this->fileService->allowUndo());
	}

	public function testMarkAsDeleted() {
		// TODO: use proper ITimeFactory in the service so we can mock the call to time
		$attachment = $this->getAttachment();
		$this->assertEquals(0, $attachment->getDeletedAt());
		$this->fileService->markAsDeleted($attachment);
		$this->assertGreaterThan(0, $attachment->getDeletedAt());
	}
}
