<?php

use Behat\Behat\Context\Context;
use GuzzleHttp\Cookie\CookieJar;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../../build/integration/features/bootstrap/autoload.php';

// The server's integration autoloader ships its own Behat; keep the app's version in front
$appLoader = require __DIR__ . '/../../vendor/autoload.php';
$appLoader->unregister();
$appLoader->register(true);

class ServerContext implements Context {
	use WebDav {
		WebDav::__construct as private __tConstruct;
	}

	private string $rawBaseUrl;
	private string $mappedUserId;
	private array $lastInsertIds = [];

	public function __construct($baseUrl) {
		$this->rawBaseUrl = $baseUrl;

		$testServerUrl = getenv('BEHAT_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->rawBaseUrl = rtrim($testServerUrl, '/');
		}

		$this->__tConstruct($this->rawBaseUrl . '/ocs/', ['admin', 'admin'], '123456');
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
		return $this->cookieJar;
	}

	public function getReqestToken(): string {
		return $this->requestToken;
	}

	public function getCurrentUser(): string {
		return $this->currentUser;
	}
}
