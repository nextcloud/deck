<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

class SessionContext implements Context {
	use RequestTrait;

	private ServerContext $serverContext;
	private BoardContext $boardContext;
	private array $tokens = [];

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext('ServerContext');
		$this->boardContext = $environment->getContext('BoardContext');
	}

	/**
	 * @Given user opens the board named :name
	 */
	public function opensTheBoardNamed($name) {
		$this->boardContext->fetchesTheBoardNamed($name);

		$board = $this->boardContext->getLastUsedBoard();
		$this->requestContext->sendOCSRequest('PUT', '/apps/deck/api/v1.0/session/create', [
			'boardId' => $board['id'],
		]);
		$res = json_decode((string)$this->getResponse()->getBody(), true);
		Assert::assertArrayHasKey('token', $res['ocs']['data'], 'session creation did not respond with a token');

		// store token
		$user = $this->serverContext->getCurrentUser();
		$this->tokens[$user] = $res['ocs']['data']['token'];
	}

	/**
	 * @Then the response should have a list of active sessions with the length :length
	 */
	public function theResponseShouldHaveActiveSessions($length) {
		$board = $this->boardContext->getLastUsedBoard();
		Assert::assertEquals($length, count($board['activeSessions']), 'unexpected count of active sessions');
	}

	/**
	 * @Then the user :user should be in the list of active sessions
	 */
	public function theUserShouldBeInTheListOfActiveSessions($user) {
		$board = $this->boardContext->getLastUsedBoard();
		Assert::assertContains($user, $board['activeSessions'], 'user is not found in the list of active sessions');
	}

	/**
	 * @When user closes the board named :name
	 */
	public function closingTheBoardNamed($name) {
		$board = $this->boardContext->getLastUsedBoard();
		if (!$board || $board['title'] != $name) {
			$this->boardContext->fetchesTheBoardNamed($name);
			$board = $this->boardContext->getLastUsedBoard();
		}

		$user = $this->serverContext->getCurrentUser();
		$token = $this->tokens[$user];
		Assert::assertNotEmpty($token, 'no token for the user found');
		$this->requestContext->sendOCSRequest('POST', '/apps/deck/api/v1.0/session/close', [
			'boardId' => $board['id'],
			'token' => $token
		]);
	}
}
