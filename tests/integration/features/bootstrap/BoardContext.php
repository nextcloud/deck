<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

class BoardContext implements Context {
	use RequestTrait;

	/** @var array Last board response */
	private $board = null;
	/** @var array last stack response */
	private $stack = null;
	/** @var array last card response */
	private $card = null;

	/** @var ServerContext */
	private $serverContext;

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext('ServerContext');
	}

	public function getLastUsedCard() {
		return $this->card;
	}

	/**
	 * @Given /^creates a board named "([^"]*)" with color "([^"]*)"$/
	 */
	public function createsABoardNamedWithColor($title, $color) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/boards', [
			'title' => $title,
			'color' => $color
		]);
		$this->getResponse()->getBody()->seek(0);
		$this->board = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @When /^fetches the board named "([^"]*)"$/
	 */
	public function fetchesTheBoardNamed($boardName) {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $this->board['id'], []);
		$this->getResponse()->getBody()->seek(0);
		$this->board = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @When shares the board with user :user
	 */
	public function sharesTheBoardWithUser($user, TableNode $permissions = null) {
		$defaults = [
			'permissionEdit' => '0',
			'permissionShare' => '0',
			'permissionManage' => '0'
		];
		$tableRows = isset($permissions) ? $permissions->getRowsHash() : [];
		$result = array_merge($defaults, $tableRows);
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
			'type' => 0,
			'participant' => $user,
			'permissionEdit' => $result['permissionEdit'] === '1',
			'permissionShare' => $result['permissionShare'] === '1',
			'permissionManage' => $result['permissionManage'] === '1',
		]);
	}

	/**
	 * @When shares the board with group :group
	 */
	public function sharesTheBoardWithGroup($group, TableNode $permissions = null) {
		$defaults = [
			'permissionEdit' => '0',
			'permissionShare' => '0',
			'permissionManage' => '0'
		];
		$tableRows = isset($permissions) ? $permissions->getRowsHash() : [];
		$result = array_merge($defaults, $tableRows);
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
			'type' => 1,
			'participant' => $group,
			'permissionEdit' => $result['permissionEdit'] === '1',
			'permissionShare' => $result['permissionShare'] === '1',
			'permissionManage' => $result['permissionManage'] === '1',
		]);
	}


	/**
	 * @When /^fetching the board list$/
	 */
	public function fetchingTheBoardList() {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards');
	}

	/**
	 * @When /^fetching the board with id "([^"]*)"$/
	 */
	public function fetchingTheBoardWithId($id) {
		$this->requestContext->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $id);
	}

	/**
	 * @Given /^create a stack named "([^"]*)"$/
	 */
	public function createAStackNamed($name) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/stacks', [
			'title' => $name,
			'boardId' => $this->board['id']
		]);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$this->stack = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @Given /^create a card named "([^"]*)"$/
	 */
	public function createACardNamed($name) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/cards', [
			'title' => $name,
			'stackId' => $this->stack['id']
		]);
		$this->requestContext->getResponse()->getBody()->seek(0);
		$this->card = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @Then /^the current user should have "(read|edit|share|manage)" permissions on the board$/
	 */
	public function theCurrentUserShouldHavePermissionsOnTheBoard($permission) {
		Assert::assertTrue($this->getPermissionsValue($permission));
	}

	/**
	 * @Then /^the current user should not have "(read|edit|share|manage)" permissions on the board$/
	 */
	public function theCurrentUserShouldNotHavePermissionsOnTheBoard($permission) {
		Assert::assertFalse($this->getPermissionsValue($permission));
	}

	private function getPermissionsValue($permission) {
		$mapping = [
			'read' => 'PERMISSION_READ',
			'edit' => 'PERMISSION_EDIT',
			'share' => 'PERMISSION_SHARE',
			'manage' => 'PERMISSION_MANAGE',
		];
		return $this->board['permissions'][$mapping[$permission]];
	}

	/**
	 * @When /^share the file "([^"]*)" with the card$/
	 */
	public function shareWithTheCard($file) {
		$table = new TableNode([
			['path', $file],
			['shareType', 12],
			['shareWith', (string)$this->card['id']],
		]);
		$this->serverContext->creatingShare($table);
	}

	/**
	 * @Given /^set the description to "([^"]*)"$/
	 */
	public function setTheDescriptionTo($description) {
		$this->requestContext->sendJSONrequest('PUT', '/index.php/apps/deck/cards/' . $this->card['id'], array_merge(
			$this->card,
			['description' => $description]
		));
		$this->requestContext->getResponse()->getBody()->seek(0);
		$this->card = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @Given /^set the card attribute "([^"]*)" to "([^"]*)"$/
	 */
	public function setCardAttribute($attribute, $value) {
		$this->requestContext->sendJSONrequest('PUT', '/index.php/apps/deck/cards/' . $this->card['id'], array_merge(
			$this->card,
			[$attribute => $value]
		));
		$this->requestContext->getResponse()->getBody()->seek(0);
		$this->card = json_decode((string)$this->getResponse()->getBody(), true);
	}

	/**
	 * @Given /^set the card duedate to "([^"]*)"$/
	 */
	public function setTheCardDuedateTo($arg1) {
		$date = new DateTime($arg1);
		$this->setCardAttribute('duedate', $date->format(DateTimeInterface::ATOM));
	}

	/**
	 * @Given /^assign the card to the user "([^"]*)"$/
	 */
	public function assignTheCardToTheUser($user) {
		$this->assignToCard($user, 0);
	}

	/**
	 * @Given /^assign the card to the group "([^"]*)"$/
	 */
	public function assignTheCardToTheGroup($user) {
		$this->assignToCard($user, 1);
	}

	private function assignToCard($participant, $type) {
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/cards/' . $this->card['id'] .'/assign', [
			'userId' => $participant,
			'type' => $type
		]);
		$this->requestContext->getResponse()->getBody()->seek(0);
	}

	/**
	 * @Given /^assign the tag "([^"]*)" to the card$/
	 */
	public function assignTheTagToTheCard($tag) {
		$filteredLabels = array_filter($this->board['labels'], function ($label) use ($tag) {
			return $label['title'] === $tag;
		});
		$label = array_shift($filteredLabels);
		$this->requestContext->sendJSONrequest('POST', '/index.php/apps/deck/cards/' . $this->card['id'] .'/label/' . $label['id']);
		$this->requestContext->getResponse()->getBody()->seek(0);
	}
}
