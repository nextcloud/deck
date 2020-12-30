<?php

use Behat\Behat\Context\Context;
use GuzzleHttp\Cookie\CookieJar;

require_once __DIR__ . '/../../vendor/autoload.php';

class ServerContext implements Context {
	use WebDav;

	/** @var string */
	private $mappedUserId;

	private $lastInsertIds = [];

	/**
	 * @BeforeSuite
	 */
	public static function addFilesToSkeleton() {
	}

	/**
	 * @Given /^acting as user "([^"]*)"$/
	 */
	public function actingAsUser($user) {
		$this->loggingInUsingWebAs($user);
		$this->asAn($user);
	}

	public function getCookieJar(): CookieJar {
		return $this->cookieJar;
	}

	public function getReqestToken(): string {
		return $this->requestToken;
	}
}
