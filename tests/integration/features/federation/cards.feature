@federation
Feature: Federation card operations
  Managing cards on federated boards

  Background:
    Given using server "LOCAL"
    And federation is enabled on "LOCAL"
    And user "admin" exists on "LOCAL"
    Given using server "REMOTE"
    And federation is enabled on "REMOTE"
    And user "admin" exists on "REMOTE"

  Scenario: Create a card on a federated board
    Given using server "LOCAL"
    And acting as user "admin"
    And creates a board named "Card Board" with color "ff0000"
    And create a stack named "ToDo"
    When user "admin" on "LOCAL" shares the board with federated user "admin"
      | permissionEdit | 1 |
    And user "admin" on "REMOTE" creates a card "Remote Card" on stack "ToDo" on the federated board
    Then the OCS response should have status code "200"

	Scenario: Assign user on a federated board
		Given using server "LOCAL"
		And acting as user "admin"
		And creates a board named "Assigning Board" with color "ff0000"
    And create a stack named "ToDo"
    When user "admin" on "LOCAL" shares the board with federated user "admin"
      | permissionEdit | 1 |
    And user "admin" on "REMOTE" creates a card "Remote Card" on stack "ToDo" on the federated board
		And user "admin" on "REMOTE" assigns user "admin" to card "Remote Card" on the federated board
		Then user "admin" on "LOCAL" should see assigned user "admin" on card "Remote Card" on the federated board "Assigning Board"
		Then user "admin" on "REMOTE" should see assigned user "admin" on card "Remote Card" on the federated board "Assigning Board"

