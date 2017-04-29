Feature: decks

  Background:
    Given user "user0" exists

  Scenario: Request the main frontend page
    Given Logging in using web as "admin"
    When Sending a "GET" to "/index.php/apps/deck" without requesttoken
    Then the HTTP status code should be "200"

  Scenario: Fetch the board list
    Given Logging in using web as "admin"
    When Sending a "GET" to "/index.php/apps/deck/boards" with requesttoken
    Then the HTTP status code should be "200"
    And the Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of a nonexisting board
    Given Logging in using web as "admin"
    When Sending a "GET" to "/index.php/apps/deck/boards/13379" with requesttoken
    Then the HTTP status code should be "403"
    And the Content-Type should be "application/json; charset=utf-8"

  Scenario: Create a new board
    Given Logging in using web as "admin"
    When Sending a "POST" to "/index.php/apps/deck/boards" with JSON
      """
      {
        "title": "MyBoard",
        "color": "000000"
     }
     """
    Then the HTTP status code should be "200"
    And the Content-Type should be "application/json; charset=utf-8"
    And the response should be a JSON array with the following mandatory values
      |key|value|
      |title|MyBoard|
      |color|000000|
