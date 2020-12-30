<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use Behat\Gherkin\Node\PyStringNode;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;

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

	public function getCookieJar(): CookieJar {
		return $this->cookieJar;
	}

	public function getReqestToken(): string {
		return $this->requestToken;
	}
}
