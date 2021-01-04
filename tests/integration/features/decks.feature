Feature: decks

  Background:
    Given user "admin" exists
    Given user "user0" exists

  Scenario: Request the main frontend page
    Given Logging in using web as "admin"
    When Sending a "GET" to "/index.php/apps/deck" without requesttoken
    Then the HTTP status code should be "200"

  Scenario: Fetch the board list
    Given Logging in using web as "admin"
    When fetching the board list
    Then the response should have a status code "200"
    And the response Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of a nonexisting board
    Given Logging in using web as "admin"
    When fetching the board with id "99999999"
    Then the response should have a status code "403"
    And the response Content-Type should be "application/json; charset=utf-8"

  Scenario: Create a new board
    Given Logging in using web as "admin"
    When creates a board named "MyBoard" with color "000000"
    Then the response should have a status code "200"
    And the response Content-Type should be "application/json; charset=utf-8"
    And the response should be a JSON array with the following mandatory values
      |key|value|
      |title|MyBoard|
      |color|000000|
