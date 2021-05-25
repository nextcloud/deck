Feature: decks

  Background:
    Given user "admin" exists
    Given user "user0" exists

  Scenario: Create a new board
    Given Using web as user "admin"
    When creates a board named "MyBoard" with color "000000"
    Then the HTTP status code should be "200"
    And the Content-Type should be "application/json; charset=utf-8"
    And the response should be a JSON array with the following mandatory values
      |key|value|
      |title|MyBoard|
      |color|000000|
