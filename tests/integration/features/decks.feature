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

  Scenario: Fail to create a board with invalid parameters
    Given acting as user "user0"
    When creates a board named "This is a very ong name that exceeds the maximum length of a deck board created which is longer than 100 characters" with color "ff0000"
    Then the response should have a status code 400
    When creates a board named "Example board" with color "invalid"
    Then the response should have a status code 400

  Scenario: Fail to create a list with invalid parameters
    Given acting as user "user0"
    And creates a board named "MyBoard" with color "000000"
    When create a stack named "This is a very ong name that exceeds the maximum length of a deck board created which is longer than 100 characters"
    Then the response should have a status code 400

  Scenario: Fail to create a card with invalid parameters
    Given acting as user "user0"
    And creates a board named "MyBoard" with color "000000"
    And create a stack named "ToDo"
    When create a card named "This is a very ong name that exceeds the maximum length of a deck board created which is longer than 255 characters This is a very ong name that exceeds the maximum length of a deck board created which is longer than 255 characters This is a very ong name that exceeds the maximum length of a deck board created which is longer than 255 characters"

	Scenario: Setting a duedate on a card
		Given acting as user "user0"
		And creates a board named "MyBoard" with color "000000"
		And create a stack named "ToDo"
		And create a card named "Overdue task"
		When get the card details
		And the response should be a JSON array with the following mandatory values
			|key|value|
			|title|Overdue task|
			|duedate||
			|overdue|0|
		And set the card attribute "duedate" to "2020-12-12 13:37:00"
		When get the card details
		And the response should be a JSON array with the following mandatory values
			|key|value|
			|title|Overdue task|
			|duedate|2020-12-12T13:37:00+00:00|
			|overdue|3|
		And set the card attribute "duedate" to ""
		When get the card details
		And the response should be a JSON array with the following mandatory values
			|key|value|
			|title|Overdue task|
			|duedate||
			|overdue|0|
