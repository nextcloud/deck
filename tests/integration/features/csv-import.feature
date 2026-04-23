Feature: CSV Import

  Background:
    Given user "admin" exists
    Given user "user0" exists

  Scenario: Import a new board from CSV
    Given Logging in using web as "admin"
    When importing a board from CSV file with content
      """
      "Card title"	"Description"	"List name"	"Tags"	"Due date"	"Created"	"Modified"
      "First card"	"A description"	"To Do"	"Feature, Bug,"	"null"	"01/02/2026"	"15/03/2026"
      "Second card"	""	"Done"	"Feature,"	"2026-03-20T19:00:00+00:00"	"10/02/2026"	"20/03/2026"
      "Third card"	"Line 1
Line 2"	"To Do"	""	"null"	"05/03/2026"	"05/03/2026"
      """
    Then the response should have a status code "200"
    When fetches the board named "import"
    Then the board should have 2 stacks
    And the board should have a stack named "To Do"
    And the board should have a stack named "Done"
    And the stack "To Do" should have 2 cards
    And the stack "Done" should have 1 cards

  Scenario: Import cards from CSV into an existing board
    Given Logging in using web as "admin"
    And creates a board named "MyBoard" with color "000000"
    And create a stack named "To Do"
    And create a card named "Existing card"
    When importing CSV cards into the current board with content
      """
      "Card title"	"Description"	"List name"	"Tags"	"Due date"	"Created"	"Modified"
      "Imported card 1"	"Some description"	"To Do"	"Urgent,"	"null"	"01/01/2026"	"01/01/2026"
      "Imported card 2"	""	"New Stack"	""	"null"	"02/01/2026"	"02/01/2026"
      """
    Then the response should have a status code "200"
    When fetches the board named "MyBoard"
    Then the board should have 2 stacks
    And the board should have a stack named "To Do"
    And the board should have a stack named "New Stack"
    And the stack "To Do" should have 2 cards
    And the stack "New Stack" should have 1 cards

  Scenario: Import CSV with labels creates labels on the board
    Given Logging in using web as "admin"
    And creates a board named "LabelBoard" with color "000000"
    And create a stack named "To Do"
    When importing CSV cards into the current board with content
      """
      "Card title"	"Description"	"List name"	"Tags"	"Due date"	"Created"	"Modified"
      "Card A"	""	"To Do"	"Feature, Bug,"	"null"	"01/01/2026"	"01/01/2026"
      "Card B"	""	"To Do"	"Feature,"	"null"	"01/01/2026"	"01/01/2026"
      """
    Then the response should have a status code "200"
    When fetches the board named "LabelBoard"
    Then the board should have labels "Feature, Bug"

  Scenario: Import CSV with various date formats
    Given Logging in using web as "admin"
    And creates a board named "DateBoard" with color "000000"
    And create a stack named "To Do"
    When importing CSV cards into the current board with content
      """
      "Card title"	"Description"	"List name"	"Tags"	"Due date"	"Created"	"Modified"
      "ISO 8601 due"	""	"To Do"	""	"2026-03-20T19:00:00+00:00"	"23/02/2026"	"28/03/2026"
      "ISO date due"	""	"To Do"	""	"2026-06-15"	"2026-01-10"	"2026-06-15"
      "Dot format dates"	""	"To Do"	""	"null"	"15.03.2026"	"20.3.2026"
      "Slash Y/m/d dates"	""	"To Do"	""	"null"	"2026/3/15"	"2026/03/20"
      "Null due date"	""	"To Do"	""	"null"	"01/02/2026"	"01/02/2026"
      "Empty due date"	""	"To Do"	""	""	"01/02/2026"	"01/02/2026"
      """
    Then the response should have a status code "200"
    When fetches the board named "DateBoard"
    And the stack "To Do" should have 6 cards
    Then the card "ISO 8601 due" should have duedate "2026-03-20T19:00:00+00:00"
    And the card "ISO date due" should have duedate "2026-06-15T00:00:00+00:00"
    And the card "Null due date" should not have a duedate
    And the card "Empty due date" should not have a duedate

  Scenario: Re-import CSV with card ID updates existing card
    Given Logging in using web as "admin"
    And creates a board named "UpdateBoard" with color "000000"
    And create a stack named "To Do"
    And create a card named "Original title"
    When importing CSV cards into the current board with content
      """
      "ID"	"Card title"	"Description"	"List name"	"Tags"	"Due date"	"Created"	"Modified"
      "{lastCardId}"	"Updated title"	"New description"	"To Do"	""	"null"	"01/01/2026"	"01/01/2026"
      ""	"Brand new card"	""	"To Do"	""	"null"	"01/01/2026"	"01/01/2026"
      """
    Then the response should have a status code "200"
    When fetches the board named "UpdateBoard"
    Then the board should have a stack named "To Do"
    And the stack "To Do" should have 2 cards
    And the card "Updated title" should have description "New description"

  Scenario: Reject non-CSV file for card import
    Given Logging in using web as "admin"
    And creates a board named "MyBoard" with color "000000"
    When importing a non-CSV file into the current board
    Then the response should have a status code "400"
