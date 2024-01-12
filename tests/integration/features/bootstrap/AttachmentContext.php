<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

class AttachmentContext implements Context {
	use RequestTrait;

	/** @var BoardContext */
	protected $boardContext;
	/** @var ServerContext */
	private $serverContext;

	protected $lastAttachment = null;
	protected array $rememberedAttachments = [];

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->boardContext = $environment->getContext('BoardContext');
		$this->serverContext = $environment->getContext('ServerContext');
	}

	public function delete(int $cardId, int $attachmentId) {
		$this->requestContext->sendPlainRequest('DELETE', '/index.php/apps/deck/cards/' . $cardId . '/attachment/file:' . $attachmentId);
		$response = $this->requestContext->getResponseBodyFromJson();
	}

	/**
	 * @When deleting the attachment :attachmentReference for the card :cardReference
	 */
	public function deletingTheAttachmentForTheCard($attachmentReference, $cardReference) {
		$cardId = $this->boardContext->getRememberedCard($cardReference)['id'] ?? null;
		$attachmentId = $this->getRememberedAttachment($attachmentReference)['id'] ?? null;
		Assert::assertNotNull($cardId, 'Card needs to be available');
		Assert::assertNotNull($attachmentId, 'Attachment needs to be available');
		$this->delete($cardId, $attachmentId);
	}

	/**
	 * @Given /^uploads an attachment to the last used card$/
	 */
	public function uploadsAnAttachmentToTheLastUsedCard() {
		$cardId = $this->boardContext->getLastUsedCard()['id'] ?? null;
		Assert::assertNotNull($cardId, 'Card data is not set');

		$this->requestContext->sendPlainRequest('POST', '/index.php/apps/deck/cards/' . $cardId . '/attachment', [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => 'Example content',
					'filename' => 'test.txt',
				],
				[
					'name' => 'type',
					'contents' => 'file'
				]
			]
		]);
	}

	/**
	 * @Given remember the last attachment as :arg1
	 */
	public function rememberTheLastAttachmentAs($arg1) {
		$this->requestContext->theResponseShouldHaveStatusCode(200);
		$this->lastAttachment = $this->requestContext->getResponseBodyFromJson();
		$this->rememberedAttachments[$arg1] = $this->lastAttachment;
	}

	public function getRememberedAttachment($name) {
		return $this->rememberedAttachments[$name] ?? null;
	}

	/**
	 * @When fetching the attachment :attachmentReference for the card :cardReference
	 */
	public function fetchingTheAttachmentForTheCard($attachmentReference, $cardReference) {
		$cardId = $this->boardContext->getRememberedCard($cardReference)['id'] ?? null;
		$attachmentId = $this->getRememberedAttachment($attachmentReference)['id'] ?? null;
		Assert::assertNotNull($cardId, 'Card needs to be available');
		Assert::assertNotNull($attachmentId, 'Attachment needs to be available');

		$this->requestContext->sendPlainRequest('GET', '/index.php/apps/deck/cards/' . $cardId . '/attachment/file:' . $attachmentId);
	}

	/**
	 * @When fetching the attachments for the card :cardReference
	 */
	public function fetchingTheAttachmentsForTheCard($cardReference) {
		$cardId = $this->boardContext->getRememberedCard($cardReference)['id'] ?? null;
		Assert::assertNotNull($cardId, 'Card needs to be available');

		$this->requestContext->sendPlainRequest('GET', '/index.php/apps/deck/cards/' . $cardId . '/attachments');
	}
}
