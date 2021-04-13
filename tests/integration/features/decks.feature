Feature: decks

  Background:
    Given user "admin" exists
    Given user "user0" exists

  Scenario: Create a new board
    Given Logging in using web as "admin"
    When creates a board named "MyBoard" with color "000000"
    Then the response should have a status code "200"
    And the response Content-Type should be "application/json; charset=utf-8"
    And the response should be a JSON array with the following mandatory values
      |key|value|
      |title|MyBoard|
      |color|000000|
