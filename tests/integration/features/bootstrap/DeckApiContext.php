<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\Assert;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Steps for the public REST API documented at https://deck.readthedocs.io/en/stable/API
 *
 * The endpoints live below /index.php/apps/deck/api/v{apiVersion} and are addressed
 * with the very same paths as in the documentation, so the feature files can be read
 * side by side with the docs.
 *
 * Ids that are only known at runtime are handled by storing values of a previous
 * response and referencing them as <placeholder> in a later endpoint or value.
 */
class DeckApiContext implements Context {
	use RequestTrait;

	private ServerContext $serverContext;

	private string $apiVersion = '1.0';

	/** @var array<string, mixed> Values remembered from previous responses */
	private array $storedValues = [];

	/** @BeforeScenario */
	public function gatherContexts(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->serverContext = $environment->getContext('ServerContext');
		$this->apiVersion = '1.0';
		$this->storedValues = [];
	}

	/**
	 * @Given /^using the Deck API version "([^"]*)"$/
	 */
	public function usingTheDeckApiVersion(string $version): void {
		$this->apiVersion = $version;
	}

	/**
	 * @When /^sending "([^"]*)" to the API endpoint "([^"]*)"$/
	 * @When /^sending "([^"]*)" to the API endpoint "([^"]*)" with body:$/
	 */
	public function sendingToTheApiEndpoint(string $method, string $endpoint, ?TableNode $body = null): void {
		$options = $body === null
			? []
			: ['json' => $this->parseBody($body)];
		$this->sendRequest($method, $endpoint, $options);
	}

	/**
	 * The OCS part of the API is served below /ocs/v2.php/apps/deck/api/v{apiVersion}
	 * and wraps its payload into an "ocs" envelope.
	 *
	 * @When /^sending "([^"]*)" to the OCS API endpoint "([^"]*)"$/
	 * @When /^sending "([^"]*)" to the OCS API endpoint "([^"]*)" with body:$/
	 */
	public function sendingToTheOcsApiEndpoint(string $method, string $endpoint, ?TableNode $body = null): void {
		$this->requestContext->sendOCSRequest(
			strtoupper($method),
			'/apps/deck/api/v' . $this->apiVersion . '/' . ltrim($this->resolve($endpoint), '/'),
			$body === null ? [] : $this->parseBody($body)
		);
	}

	/**
	 * @When /^sending "([^"]*)" to the API endpoint "([^"]*)" with the header "([^"]*)" set to "([^"]*)"$/
	 */
	public function sendingToTheApiEndpointWithHeader(string $method, string $endpoint, string $header, string $value): void {
		$this->sendRequest($method, $endpoint, [], [$header => $this->resolve($value)]);
	}

	/**
	 * Integrations are no browsers: they authenticate with basic auth and without
	 * a CSRF token, so the session of the current user is left out on purpose.
	 *
	 * @When /^sending "([^"]*)" to the API endpoint "([^"]*)" as "([^"]*)" with password "([^"]*)"$/
	 * @When /^sending "([^"]*)" to the API endpoint "([^"]*)" as "([^"]*)" with password "([^"]*)" and body:$/
	 */
	public function sendingToTheApiEndpointAsClient(string $method, string $endpoint, string $user, string $password, ?TableNode $body = null): void {
		$options = [
			'auth' => [$user, $password],
			'cookies' => new CookieJar(),
		];
		if ($body !== null) {
			$options['json'] = $this->parseBody($body);
		}

		$this->sendRequest($method, $endpoint, $options, [], false);
	}

	/**
	 * @When /^sending "([^"]*)" to the API endpoint "([^"]*)" without authentication$/
	 */
	public function sendingToTheApiEndpointWithoutAuthentication(string $method, string $endpoint): void {
		$this->sendRequest($method, $endpoint, ['cookies' => new CookieJar()], [], false);
	}

	/**
	 * @When /^uploading the file "([^"]*)" with content "([^"]*)" as attachment type "([^"]*)" to the API endpoint "([^"]*)"$/
	 */
	public function uploadingTheFileToTheApiEndpoint(string $filename, string $content, string $type, string $endpoint): void {
		$this->sendRequest('POST', $endpoint, [
			'multipart' => [
				[
					'name' => 'file',
					'contents' => $content,
					'filename' => $filename,
				],
				[
					'name' => 'type',
					'contents' => $type,
				],
			],
		]);
	}

	/**
	 * @Then /^the response value "([^"]*)" is stored as "([^"]*)"$/
	 */
	public function theResponseValueIsStoredAs(string $path, string $name): void {
		$this->storedValues[$name] = $this->getResponseValue($path);
	}

	/**
	 * @Then /^the response header "([^"]*)" is stored as "([^"]*)"$/
	 */
	public function theResponseHeaderIsStoredAs(string $header, string $name): void {
		$values = $this->getResponse()->getHeader($header);
		Assert::assertNotEmpty($values, 'Expected a "' . $header . '" header to be present');
		$this->storedValues[$name] = $values[0];
	}

	/**
	 * @Then /^the response value "([^"]*)" should be "([^"]*)"$/
	 */
	public function theResponseValueShouldBe(string $path, string $expected): void {
		$actual = $this->getResponseValue($path);
		$expectedValue = $this->castValue($this->resolve($expected));

		Assert::assertTrue(
			$this->valuesMatch($expectedValue, $actual),
			'Expected "' . $path . '" to be ' . json_encode($expectedValue) . ' but got ' . json_encode($actual)
		);
	}

	/**
	 * @Then /^the response value "([^"]*)" should not be "([^"]*)"$/
	 */
	public function theResponseValueShouldNotBe(string $path, string $expected): void {
		$actual = $this->getResponseValue($path);
		$expectedValue = $this->castValue($this->resolve($expected));

		Assert::assertFalse(
			$this->valuesMatch($expectedValue, $actual),
			'Expected "' . $path . '" to differ from ' . json_encode($expectedValue)
		);
	}

	/**
	 * @Then /^the response should contain the key "([^"]*)"$/
	 */
	public function theResponseShouldContainTheKey(string $path): void {
		// Reading the value asserts that every segment of the path is present
		$this->getResponseValue($path);
	}

	/**
	 * @Then /^the response should not contain the key "([^"]*)"$/
	 */
	public function theResponseShouldNotContainTheKey(string $path): void {
		$segments = explode('.', $path);
		$last = array_pop($segments);
		$parentPath = implode('.', $segments);
		$parent = $segments === []
			? $this->requestContext->getResponseBodyFromJson()
			: $this->getResponseValue($parentPath);

		Assert::assertIsArray($parent, 'Expected "' . ($parentPath ?: 'the response') . '" to be an array');
		Assert::assertArrayNotHasKey($last, $parent, 'Unexpected "' . $path . '" in the response');
	}

	/**
	 * @Then /^the response value "([^"]*)" should be empty$/
	 */
	public function theResponseValueShouldBeEmpty(string $path): void {
		Assert::assertEmpty($this->getResponseValue($path), 'Expected "' . $path . '" to be empty');
	}

	/**
	 * @Then /^the response value "([^"]*)" should have (\d+) entr(?:y|ies)$/
	 */
	public function theResponseValueShouldHaveEntries(string $path, int $count): void {
		$value = $this->getResponseValue($path);
		Assert::assertIsArray($value, 'Expected "' . $path . '" to be an array');
		Assert::assertCount($count, $value);
	}

	/**
	 * @Then /^the response list should contain (\d+) entr(?:y|ies)$/
	 */
	public function theResponseListShouldContainEntries(int $count): void {
		$body = $this->requestContext->getResponseBodyFromJson();
		Assert::assertIsArray($body, 'Expected the response to be a list');
		Assert::assertCount($count, $body);
	}

	/**
	 * @Then /^the response list should contain an entry with "([^"]*)" set to "([^"]*)"$/
	 */
	public function theResponseListShouldContainAnEntryWith(string $key, string $value): void {
		Assert::assertNotEmpty(
			$this->findEntriesWith($key, $value),
			'No entry with "' . $key . '" set to "' . $value . '" in ' . json_encode($this->requestContext->getResponseBodyFromJson())
		);
	}

	/**
	 * @Then /^the response list should not contain an entry with "([^"]*)" set to "([^"]*)"$/
	 */
	public function theResponseListShouldNotContainAnEntryWith(string $key, string $value): void {
		Assert::assertEmpty(
			$this->findEntriesWith($key, $value),
			'Unexpected entry with "' . $key . '" set to "' . $value . '"'
		);
	}

	/**
	 * @Then /^the response should have the header "([^"]*)"$/
	 */
	public function theResponseShouldHaveTheHeader(string $header): void {
		Assert::assertNotEmpty($this->getResponse()->getHeader($header), 'Expected a "' . $header . '" header to be present');
	}

	/**
	 * @Then /^the response body should be "([^"]*)"$/
	 */
	public function theResponseBodyShouldBe(string $expected): void {
		$this->getResponse()->getBody()->seek(0);
		Assert::assertSame($expected, (string)$this->getResponse()->getBody());
	}

	private function sendRequest(string $method, string $endpoint, array $options = [], array $extraHeaders = [], bool $withSession = true): void {
		$url = '/index.php/apps/deck/api/v' . $this->apiVersion . '/' . ltrim($this->resolve($endpoint), '/');

		$headers = [
			// Required for every request according to the API documentation
			'OCS-APIRequest' => 'true',
			'Accept' => 'application/json',
		];
		if ($withSession) {
			$headers['requesttoken'] = $this->serverContext->getReqestToken();
		}
		$headers = array_merge($headers, $extraHeaders);

		if (!isset($options['json']) && !isset($options['multipart'])) {
			$headers['Content-Type'] = 'application/json';
		}

		$this->requestContext->sendPlainRequest(
			strtoupper($method),
			$url,
			array_merge(['headers' => $headers], $options)
		);
	}

	private function parseBody(TableNode $body): array {
		$parsed = [];
		foreach ($body->getRowsHash() as $key => $value) {
			$parsed[$key] = $this->castValue($this->resolve($value));
		}
		return $parsed;
	}

	/**
	 * Replace <name> references with values remembered from earlier responses
	 */
	private function resolve(string $value): string {
		return preg_replace_callback('/<([a-zA-Z0-9_]+)>/', function (array $matches) {
			Assert::assertArrayHasKey($matches[1], $this->storedValues, 'No value has been stored as "' . $matches[1] . '"');
			$stored = $this->storedValues[$matches[1]];
			Assert::assertTrue(
				is_string($stored) || is_int($stored) || is_float($stored),
				'The value stored as "' . $matches[1] . '" cannot be used in a placeholder: ' . json_encode($stored)
			);
			return (string)$stored;
		}, $value);
	}

	/**
	 * Table and step values are strings, so they get converted to the type that
	 * the API expects. Wrapping a value in quotes keeps it a string.
	 *
	 * @return mixed
	 */
	private function castValue(string $value) {
		if (preg_match('/^"(.*)"$/s', $value, $matches)) {
			return $matches[1];
		}
		if ($value === 'null') {
			return null;
		}
		if ($value === 'true' || $value === 'false') {
			return $value === 'true';
		}
		if (preg_match('/^-?\d+$/', $value)) {
			return (int)$value;
		}
		return $value;
	}

	/**
	 * @return mixed
	 */
	private function getResponseValue(string $path) {
		$value = $this->requestContext->getResponseBodyFromJson();
		foreach (explode('.', $path) as $segment) {
			Assert::assertIsArray($value, 'Cannot read "' . $segment . '" of "' . $path . '" as it is no array');
			Assert::assertArrayHasKey($segment, $value, 'Missing "' . $segment . '" of "' . $path . '" in ' . json_encode($value));
			$value = $value[$segment];
		}
		return $value;
	}

	private function findEntriesWith(string $key, string $value): array {
		$body = $this->requestContext->getResponseBodyFromJson();
		Assert::assertIsArray($body, 'Expected the response to be a list');
		$expected = $this->castValue($this->resolve($value));

		return array_filter($body, function ($entry) use ($key, $expected) {
			return is_array($entry)
				&& array_key_exists($key, $entry)
				&& $this->valuesMatch($expected, $entry[$key]);
		});
	}

	/**
	 * Booleans and null have to match exactly, everything else is compared
	 * loosely so that values the API returns as a string - ids for example -
	 * still match the numeric value used in a feature file.
	 *
	 * @param mixed $expected
	 * @param mixed $actual
	 */
	private function valuesMatch($expected, $actual): bool {
		if (is_bool($expected) || $expected === null) {
			return $expected === $actual;
		}
		if (is_array($actual)) {
			return false;
		}
		return $expected == $actual;
	}
}
