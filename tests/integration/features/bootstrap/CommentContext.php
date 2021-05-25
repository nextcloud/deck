<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use JuliusHaertl\NextcloudBehat\Context\ServerContext;

require_once __DIR__ . '/../../vendor/autoload.php';

class CommentContext implements Context {

	/** @var ServerContext */
	protected $serverContext;
	/** @var BoardContext */
	protected $boardContext;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext(ServerContext::class);
		$this->boardContext = $environment->getContext('BoardContext');
	}

	/**
	 * @Given /^post a comment with content "([^"]*)" on the card$/
	 */
	public function postACommentWithContentOnTheCard($content) {
		$card = $this->boardContext->getLastUsedCard();
		$this->serverContext->sendOCSRequest('POST', '/apps/deck/api/v1.0/cards/' . $card['id'] . '/comments', [
			'message' => $content,
			'parentId' => null
		]);
	}
}
