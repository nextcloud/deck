Feature: REST API - Authentication
  The API is meant to be consumed by external integrations rather than by a
  browser, so it has to stay reachable with basic auth and without a CSRF
  token, see https://deck.readthedocs.io/en/stable/API/#prerequisites

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"
    And sending "POST" to the API endpoint "/boards" with body:
      | title | Integration board |
      | color | ff0000            |
    And the response value "id" is stored as "boardId"

  Scenario: Unauthenticated requests are rejected
    When sending "GET" to the API endpoint "/boards" without authentication
    Then the response should have a status code "401"
    When sending "GET" to the API endpoint "/boards/<boardId>" without authentication
    Then the response should have a status code "401"

  # The password is the one the shared behat provisioning steps assign to new users
  Scenario: A client can use the API with basic auth and without a CSRF token
    When sending "GET" to the API endpoint "/boards" as "user0" with password "123456"
    Then the response should have a status code "200"
    And the response list should contain an entry with "title" set to "Integration board"
    When sending "GET" to the API endpoint "/boards/<boardId>" as "user0" with password "123456"
    Then the response should have a status code "200"
    And the response value "title" should be "Integration board"

  Scenario: Basic auth is bound to the authenticated user
    When sending "GET" to the API endpoint "/boards/<boardId>" as "user1" with password "123456"
    Then the response should have a status code "403"
    And the response value "message" should be "Permission denied"
    When sending "GET" to the API endpoint "/boards" as "user1" with password "123456"
    Then the response should have a status code "200"
    And the response list should not contain an entry with "title" set to "Integration board"

  Scenario: A client can write through the API with basic auth
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks" as "user0" with password "123456" and body:
      | title | Stack from a client |
      | order | 1                   |
    Then the response should have a status code "200"
    And the response value "title" should be "Stack from a client"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks"
    Then the response should have a status code "200"
    And the response list should contain an entry with "title" set to "Stack from a client"
