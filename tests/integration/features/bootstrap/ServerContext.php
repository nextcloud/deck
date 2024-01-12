<?php

use Behat\Behat\Context\Context;
use GuzzleHttp\Cookie\CookieJar;

require_once __DIR__ . '/../../vendor/autoload.php';

class ServerContext implements Context {
	use WebDav {
		WebDav::__construct as private __tConstruct;
	}

	private $rawBaseUrl;
	private $mappedUserId;
	private $lastInsertIds = [];

	public function __construct($baseUrl) {
		$this->rawBaseUrl = $baseUrl;
		$this->__tConstruct($baseUrl . '/index.php/ocs/', ['admin', 'admin'], '123456');
	}


	/**
	 * @BeforeSuite
	 */
	public static function addFilesToSkeleton() {
	}

	/**
	 * @Given /^acting as user "([^"]*)"$/
	 */
	public function actingAsUser($user) {
		$this->cookieJar = new CookieJar();
		$this->loggingInUsingWebAs($user);
		$this->asAn($user);
	}

	public function getBaseUrl(): string {
		return $this->rawBaseUrl;
	}

	public function getCookieJar(): CookieJar {
		echo $this->currentUser;
		return $this->cookieJar;
	}

	public function getReqestToken(): string {
		return $this->requestToken;
	}
}
