<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentContext implements Context {
	use RequestTrait;

	/** @var BoardContext */
	protected $boardContext;

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
	}
}
