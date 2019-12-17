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


use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\IAppContainer;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/** @internal Just for testing the service registration */
class MyAttachmentService {
	public function extendData(Attachment $attachment) {}
	public function display(Attachment $attachment) {}
	public function create(Attachment $attachment) {}
	public function update(Attachment $attachment) {}
	public function delete(Attachment $attachment) {}
	public function allowUndo() {}
	public function markAsDeleted(Attachment $attachment) {}
}

class AttachmentServiceTest extends TestCase {

	/** @var AttachmentMapper|MockObject */
	private $attachmentMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var PermissionService|MockObject */
	private $permissionService;
	private $userId = 'admin';
	/** @var Application|MockObject */
	private $application;
	private $cacheFactory;
	/** @var AttachmentService */
	private $attachmentService;
	/** @var MockObject */
	private $attachmentServiceImpl;
	/** @var ActivityManager  */
	private $activityManager;
	private $appContainer;
	/** ICache */
	private $cache;
	/** @var IL10N */
	private $l10n;
	/** @var ChangeHelper */
	private $changeHelper;

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function setUp(): void {
		parent::setUp();

		$this->attachmentServiceImpl = $this->createMock(IAttachmentService::class);
		$this->appContainer = $this->createMock(IAppContainer::class);

		$this->attachmentMapper = $this->createMock(AttachmentMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->application = $this->createMock(Application::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->activityManager = $this->createMock(ActivityManager::class);

		$this->cache = $this->createMock(ICache::class);
		$this->cacheFactory->expects($this->any())->method('createDistributed')->willReturn($this->cache);

		$this->appContainer->expects($this->at(0))->method('query')->with(FileService::class)->willReturn($this->attachmentServiceImpl);
		$this->application->expects($this->any())
			->method('getContainer')
			->willReturn($this->appContainer);

		$this->l10n = $this->createMock(IL10N::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);

        $this->attachmentService = new AttachmentService($this->attachmentMapper, $this->cardMapper, $this->changeHelper, $this->permissionService, $this->application, $this->cacheFactory, $this->userId, $this->l10n, $this->activityManager);
    }

    public function testRegisterAttachmentService() {
		$application = $this->createMock(Application::class);
		$appContainer = $this->createMock(IAppContainer::class);
		$fileServiceMock = $this->createMock(FileService::class);
		$appContainer->expects($this->at(1))->method('query')->with(MyAttachmentService::class)->willReturn(new MyAttachmentService());
		$appContainer->expects($this->at(0))->method('query')->with(FileService::class)->willReturn($fileServiceMock);
		$application->expects($this->any())
			->method('getContainer')
			->willReturn($appContainer);
		$attachmentService = new AttachmentService($this->attachmentMapper, $this->cardMapper, $this->changeHelper, $this->permissionService, $application, $this->cacheFactory, $this->userId, $this->l10n, $this->activityManager);
		$attachmentService->registerAttachmentService('custom', MyAttachmentService::class);
		$this->assertEquals($fileServiceMock, $attachmentService->getService('deck_file'));
		$this->assertEquals(MyAttachmentService::class, get_class($attachmentService->getService('custom')));
	}

	public function testRegisterAttachmentServiceNotExisting() {
		$this->expectException(InvalidAttachmentType::class);
		$application = $this->createMock(Application::class);
		$appContainer = $this->createMock(IAppContainer::class);
		$fileServiceMock = $this->createMock(FileService::class);
		$appContainer->expects($this->at(0))->method('query')->with(FileService::class)->willReturn($fileServiceMock);
		$appContainer->expects($this->at(1))->method('query')->with(MyAttachmentService::class)->willReturn(new MyAttachmentService());
		$application->expects($this->any())
			->method('getContainer')
			->willReturn($appContainer);
		$attachmentService = new AttachmentService($this->attachmentMapper, $this->cardMapper, $this->changeHelper, $this->permissionService, $application, $this->cacheFactory, $this->userId, $this->l10n, $this->activityManager);
		$attachmentService->registerAttachmentService('custom', MyAttachmentService::class);
		$attachmentService->getService('deck_file_invalid');
	}

	private function mockPermission($permission) {
		$this->permissionService->expects($this->once())
			->method('checkPermission')
			->with($this->cardMapper, 123, $permission);
	}

	private function createAttachment($type, $data) {
		$attachment = new Attachment();
		$attachment->setType($type);
		$attachment->setData($data);
		return $attachment;
	}

	public function testFindAll() {
		$this->mockPermission(Acl::PERMISSION_READ);
		$attachments = [
			$this->createAttachment('deck_file','file1'),
			$this->createAttachment('deck_file','file2'),
			$this->createAttachment('deck_file_invalid','file3'),
		];
		$this->attachmentMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($attachments);

		$this->attachmentServiceImpl->expects($this->at(0))
			->method('extendData')
			->with($attachments[0]);
		$this->attachmentServiceImpl->expects($this->at(1))
			->method('extendData')
			->with($attachments[1]);
		$this->assertEquals($attachments, $this->attachmentService->findAll(123, false));
	}

	public function testFindAllWithDeleted() {
		$this->mockPermission(Acl::PERMISSION_READ);
		$attachments = [
			$this->createAttachment('deck_file','file1'),
			$this->createAttachment('deck_file','file2'),
			$this->createAttachment('deck_file_invalid','file3'),
		];
		$attachmentsDeleted = [
			$this->createAttachment('deck_file','file4'),
			$this->createAttachment('deck_file','file5'),
			$this->createAttachment('deck_file_invalid','file6'),
		];
		$this->attachmentMapper->expects($this->once())
			->method('findAll')
			->with(123)
			->willReturn($attachments);
		$this->attachmentMapper->expects($this->once())
			->method('findToDelete')
			->with(123, false)
			->willReturn($attachmentsDeleted);

		$this->attachmentServiceImpl->expects($this->at(0))
			->method('extendData')
			->with($attachments[0]);
		$this->attachmentServiceImpl->expects($this->at(1))
			->method('extendData')
			->with($attachments[1]);
		$this->assertEquals(array_merge($attachments, $attachmentsDeleted), $this->attachmentService->findAll(123, true));
	}

	public function testCount() {
		$this->cache->expects($this->once())->method('get')->with('card-123')->willReturn(null);
		$this->attachmentMapper->expects($this->once())->method('findAll')->willReturn([1,2,3,4]);
		$this->cache->expects($this->once())->method('set')->with('card-123', 4)->willReturn(null);
		$this->assertEquals(4, $this->attachmentService->count(123));
	}

	public function testCountCacheHit() {
		$this->cache->expects($this->once())->method('get')->with('card-123')->willReturn(4);
		$this->assertEquals(4, $this->attachmentService->count(123));
	}

	public function testCreate() {
		$attachment = $this->createAttachment('deck_file', 'file_name.jpg');
		$expected = $this->createAttachment('deck_file', 'file_name.jpg');
		$this->mockPermission(Acl::PERMISSION_EDIT);
		$this->cache->expects($this->once())->method('clear')->with('card-123');
		$this->attachmentServiceImpl->expects($this->once())
			->method('create');
		$this->attachmentMapper->expects($this->once())
			->method('insert')
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('extendData')
			->willReturnCallback(function($a) { $a->setExtendedData(['mime' => 'image/jpeg']); });

		$actual = $this->attachmentService->create(123, 'deck_file', 'file_name.jpg');

		$expected->setExtendedData(['mime' => 'image/jpeg']);
		$this->assertEquals($expected, $actual);
	}

	public function testDisplay() {
		$attachment = $this->createAttachment('deck_file', 'filename');
		$response = new Response();
		$this->mockPermission(Acl::PERMISSION_READ);
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('display')
			->with($attachment)
			->willReturn($response);
		$actual = $this->attachmentService->display(123, 1);
		$this->assertEquals($response, $actual);
	}

	public function testDisplayInvalid() {
		$this->expectException(NotFoundException::class);
		$attachment = $this->createAttachment('deck_file', 'filename');
		$response = new Response();
		$this->mockPermission(Acl::PERMISSION_READ);
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('display')
			->with($attachment)
			->will($this->throwException(new InvalidAttachmentType('deck_file')));
		$this->attachmentService->display(123, 1);
	}
	public function testUpdate() {
		$attachment = $this->createAttachment('deck_file', 'file_name.jpg');
		$expected = $this->createAttachment('deck_file', 'file_name.jpg');
		$this->mockPermission(Acl::PERMISSION_EDIT);
		$this->cache->expects($this->once())->method('clear')->with('card-123');
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('update');
		$this->attachmentMapper->expects($this->once())
			->method('update')
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('extendData')
			->willReturnCallback(function($a) { $a->setExtendedData(['mime' => 'image/jpeg']); });

		$actual = $this->attachmentService->update(123, 1, 'file_name.jpg');

		$expected->setExtendedData(['mime' => 'image/jpeg']);
		$expected->setLastModified($attachment->getLastModified());
		$this->assertEquals($expected, $actual);
	}

	public function testDelete() {
		$attachment = $this->createAttachment('deck_file', 'file_name.jpg');
		$expected = $this->createAttachment('deck_file', 'file_name.jpg');
		$this->mockPermission(Acl::PERMISSION_EDIT);
		$this->cache->expects($this->once())->method('clear')->with('card-123');
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('allowUndo')
			->willReturn(false);
		$this->attachmentServiceImpl->expects($this->once())
			->method('delete');
		$this->attachmentMapper->expects($this->once())
			->method('delete')
			->willReturn($attachment);
		$actual = $this->attachmentService->delete(123, 1);
		$this->assertEquals($expected, $actual);
	}

	public function testDeleteWithUndo() {
		$attachment = $this->createAttachment('deck_file', 'file_name.jpg');
		$expected = $this->createAttachment('deck_file', 'file_name.jpg');
		$this->mockPermission(Acl::PERMISSION_EDIT);
		$this->cache->expects($this->once())->method('clear')->with('card-123');
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('allowUndo')
			->willReturn(true);
		$this->attachmentServiceImpl->expects($this->once())
			->method('markAsDeleted')
			->willReturnCallback(function($a) { $a->setDeletedAt(23); });
		$this->attachmentMapper->expects($this->once())
			->method('update')
			->willReturn($attachment);
		$expected->setDeletedAt(23);
		$actual = $this->attachmentService->delete(123, 1);
		$this->assertEquals($expected, $actual);
	}

	public function testRestore() {
		$attachment = $this->createAttachment('deck_file', 'file_name.jpg');
		$expected = $this->createAttachment('deck_file', 'file_name.jpg');
		$this->mockPermission(Acl::PERMISSION_EDIT);
		$this->cache->expects($this->once())->method('clear')->with('card-123');
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('allowUndo')
			->willReturn(true);
		$this->attachmentMapper->expects($this->once())
			->method('update')
			->willReturn($attachment);
		$expected->setDeletedAt(0);
		$actual = $this->attachmentService->restore(123, 1);
		$this->assertEquals($expected, $actual);
	}

	public function testRestoreNotAllowed() {
		$this->expectException(NoPermissionException::class);
		$attachment = $this->createAttachment('deck_file', 'file_name.jpg');
		$expected = $this->createAttachment('deck_file', 'file_name.jpg');
		$this->mockPermission(Acl::PERMISSION_EDIT);
		$this->cache->expects($this->once())->method('clear')->with('card-123');
		$this->attachmentMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($attachment);
		$this->attachmentServiceImpl->expects($this->once())
			->method('allowUndo')
			->willReturn(false);
		$actual = $this->attachmentService->restore(123, 1);
	}

}
