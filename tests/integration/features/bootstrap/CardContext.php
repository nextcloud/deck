<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use Behat\Gherkin\Node\PyStringNode;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

class BoardContext implements Context {
	use RequestTrait;

	/** @var array Last board response */
	private $board = null;

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
    public function sharesTheBoardWithUser($user)
    {
		$this->sendJSONrequest('POST', '/index.php/apps/deck/boards/' . $this->board['id'] . '/acl', [
			'type' => 0,
			'participant' => $user,
			'permissionEdit' => true,
			'permissionShare' => true,
			'permissionManage' => true,
		]);
	}

	/**
	 * @Given /^the current user should have read permissions on the board$/
	 */
	public function theCurrentUserShouldHaveReadPermissionsOnTheBoard() {
		Assert::assertTrue($this->board['permissions']['PERMISSION_READ']);
	}

	/**
	 * @Given /^the current user should have write permissions on the board$/
	 */
	public function theCurrentUserShouldHaveWritePermissionsOnTheBoard() {
		Assert::assertTrue($this->board['permissions']['PERMISSION_EDIT']);
	}

	/**
	 * @Given /^the current user should have share permissions on the board$/
	 */
	public function theCurrentUserShouldHaveSharePermissionsOnTheBoard() {
		Assert::assertTrue($this->board['permissions']['PERMISSION_SHARE']);
	}

	/**
	 * @Given /^the current user should have manage permissions on the board$/
	 */
	public function theCurrentUserShouldHaveManagePermissionsOnTheBoard() {
		Assert::assertTrue($this->board['permissions']['PERMISSION_MANAGE']);
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

}
