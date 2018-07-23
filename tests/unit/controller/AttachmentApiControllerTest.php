<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Deck\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

use OCA\Deck\Service\AttachmentService;
use OCA\Deck\Db\Attachment;

class AttachmentApiControllerTest extends \Test\TestCase {

    private $appName = 'deck';
    private $controller;
    private $request;    
    private $attachmentExample;
    private $cardId;
    private $attachmentService;

    public function setUp() {
        parent::setUp();
        $this->attachmentExample = new Attachment();
        $this->attachmentExample->setId(1);
        $this->cardId = 1;
        $this->request = $this->createMock(IRequest::class);
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->controller = new AttachmentApiController(
            $this->appName,
            $this->request
        );
    }

    public function testGetAll() {

        $allAttachments = [$this->attachmentExample];

        $this->attachmentService->expects($this->once())
            ->method('findAll')
            ->willReturn($allAttachments);

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('cardId')
            ->willReturn($allAttachments);

        $expected = new DataResponse($this->attachmentExample, HTTP::STATUS_OK);
        $actual = $this->controller->getAll();
        $this->assertEquals($expected, $actual);
    }

    public function testDisplay() {

        $this->attachmentService->expects($this->once())
            ->method('display')
            ->willReturn($this->attachmentExample);

        $this->request->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(
                ['cardId'],
                ['attachmentId']
            )->willReturnonConsecutiveCalls(
                $this->cardId,
                $this->attachmentExample->getId());

        $expected = new DataResponse($this->attachmentExample, HTTP::STATUS_OK);
        $actual = $this->controller->display();
        $this->assertEquals($expected, $actual);
    }

    public function testCreate() {

        $this->attachmentService->expects($this->once())
            ->method('create')
            ->willReturn($this->attachmentExample);

        $this->request->expects($this->once())
            ->method('getParam')
            ->with('cardId')
            ->willReturn($this->cardId);

        $expected = new DataResponse($this->attachmentExample, HTTP::STATUS_OK);
        $actual = $this->controller->create();
        $this->assertEquals($expected, $actual);
    }

    public function testUpdate() {

        $this->attachmentService->expects($this->once())
            ->method('update')
            ->willReturn($this->attachmentExample);

        $this->request->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(
                ['cardId'],
                ['attachmentId']
            )->willReturnonConsecutiveCalls(
                $this->cardId,
                $this->attachmentExample->getId());

        $expected = new DataResponse($this->attachmentExample, HTTP::STATUS_OK);
        $actual = $this->controller->update();
        $this->assertEquals($expected, $actual);
    }

    public function testDelete() {
        $this->attachmentService->expects($this->once())
            ->method('delete')
            ->willReturn($this->attachmentExample);

        $this->request->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(
                ['cardId'],
                ['attachmentId']
            )->willReturnonConsecutiveCalls(
                $this->cardId,
                $this->attachmentExample->getId());

        $expected = new DataResponse($this->attachmentExample, HTTP::STATUS_OK);
        $actual = $this->controller->delete();
        $this->assertEquals($expected, $actual);
    }

    public function testRestore() {
        $this->attachmentService->expects($this->once())
            ->method('restore')
            ->willReturn($this->attachmentExample);

        $this->request->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(
                ['cardId'],
                ['attachmentId']
            )->willReturnonConsecutiveCalls(
                $this->cardId,
                $this->attachmentExample->getId());

        $expected = new DataResponse($this->attachmentExample, HTTP::STATUS_OK);
        $actual = $this->controller->restore();
        $this->assertEquals($expected, $actual);
    }
}