@federation
Feature: Federation stack operations
  Managing stacks on federated boards

  Background:
    Given using server "LOCAL"
    And federation is enabled on "LOCAL"
    And user "admin" exists on "LOCAL"
    Given using server "REMOTE"
    And federation is enabled on "REMOTE"
    And user "admin" exists on "REMOTE"

  Scenario: List stacks on a federated board
    Given using server "LOCAL"
    And acting as user "admin"
    And creates a board named "Stack Board" with color "ff0000"
    And create a stack named "ToDo"
    When user "admin" on "LOCAL" shares the board with federated user "admin"
      | permissionEdit | 1 |
    Then user "admin" on "REMOTE" should see 1 stack on the board "Stack Board"

  Scenario: Create a stack on a federated board
    Given using server "LOCAL"
    And acting as user "admin"
    And creates a board named "Remote Stack Board" with color "0000ff"
    When user "admin" on "LOCAL" shares the board with federated user "admin"
      | permissionEdit   | 1 |
      | permissionManage | 1 |
    And user "admin" on "REMOTE" creates a stack "New Remote Stack" on the federated board
    Then the OCS response should have status code "200"
    And user "admin" on "LOCAL" should see 1 stack on the board "Remote Stack Board"
