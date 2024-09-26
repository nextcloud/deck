<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentContext implements Context {
	use RequestTrait;

	/** @var BoardContext */
	protected $boardContext;

	private $lastComment = null;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->boardContext = $environment->getContext('BoardContext');
	}

	/**
	 * @Given /^post a comment with content "([^"]*)" on the card$/
	 */
	public function postACommentWithContentOnTheCard($content) {
		$card = $this->boardContext->getLastUsedCard();
		$this->requestContext->sendOCSRequest('POST', '/apps/deck/api/v1.0/cards/' . $card['id'] . '/comments', [
			'message' => $content,
			'parentId' => null
		]);
		$this->lastComment = $this->requestContext->getResponseBodyFromJson()['ocs']['data'] ?? null;
	}

	/**
	 * @Given /^get the comments on the card$/
	 */
	public function getCommentsOnTheCard() {
		$card = $this->boardContext->getLastUsedCard();
		$this->requestContext->sendOCSRequest('GET', '/apps/deck/api/v1.0/cards/' . $card['id'] . '/comments');
	}

	/**
	 * @When /^update a comment with content "([^"]*)" on the card$/
	 */
	public function updateACommentWithContentOnTheCard($content) {
		$card = $this->boardContext->getLastUsedCard();
		$this->requestContext->sendOCSRequest('PUT', '/apps/deck/api/v1.0/cards/' . $card['id'] . '/comments/' . $this->lastComment['id'], [
			'message' => $content,
			'parentId' => null
		]);
	}

	/**
	 * @When /^delete the comment on the card$/
	 */
	public function deleteTheCommentOnTheCard() {
		$card = $this->boardContext->getLastUsedCard();
		$this->requestContext->sendOCSRequest('DELETE', '/apps/deck/api/v1.0/cards/' . $card['id'] . '/comments/' . $this->lastComment['id']);
	}

}
