<?php

use Behat\Behat\Context\Context;
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

	/**
	 * @Given /^creates a board named "([^"]*)" with color "([^"]*)"$/
	 */
	public function createsABoardNamedWithColor($title, $color) {
		$this->sendJSONrequest('POST', '/index.php/apps/deck/boards', [
			'title' => $title,
			'color' => $color
		]);
		$this->response->getBody()->seek(0);
		$this->board = json_decode((string)$this->response->getBody(), true);
	}

	/**
	 * @When /^fetches the board named "([^"]*)"$/
	 */
	public function fetchesTheBoardNamed($boardName) {
		$this->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $this->board['id'], []);
		$this->response->getBody()->seek(0);
		$this->board = json_decode((string)$this->response->getBody(), true);
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
		$this->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
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
		$this->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
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
		$this->sendJSONrequest('GET', '/index.php/apps/deck/boards');
	}

	/**
	 * @When /^fetching the board with id "([^"]*)"$/
	 */
	public function fetchingTheBoardWithId($id) {
		$this->sendJSONrequest('GET', '/index.php/apps/deck/boards/' . $id);
	}

	/**
	 * @Given /^create a stack named "([^"]*)"$/
	 */
	public function createAStackNamed($name) {
		$this->sendJSONrequest('POST', '/index.php/apps/deck/stacks', [
			'title' => $name,
			'boardId' => $this->board['id']
		]);
		$this->response->getBody()->seek(0);
		$this->stack = json_decode((string)$this->response->getBody(), true);
	}

	/**
	 * @Given /^create a card named "([^"]*)"$/
	 */
	public function createACardNamed($name) {
		$this->sendJSONrequest('POST', '/index.php/apps/deck/cards', [
			'title' => $name,
			'stackId' => $this->stack['id']
		]);
		$this->response->getBody()->seek(0);
		$this->card = json_decode((string)$this->response->getBody(), true);
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
}
