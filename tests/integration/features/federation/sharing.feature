@federation
Feature: Federation board sharing
  Share boards across federated Nextcloud instances

  Background:
    Given using server "LOCAL"
    And federation is enabled on "LOCAL"
    And user "admin" exists on "LOCAL"
    Given using server "REMOTE"
    And federation is enabled on "REMOTE"
    And user "admin" exists on "REMOTE"

  Scenario: Share a board with a federated user
    Given using server "LOCAL"
    And acting as user "admin"
    And creates a board named "Shared Board" with color "ff0000"
    When user "admin" on "LOCAL" shares the board with federated user "admin"
    Then the OCS response should have status code "200"
    And user "admin" on "REMOTE" should see the board "Shared Board"

  Scenario: Share a board with edit permissions
    Given using server "LOCAL"
    And acting as user "admin"
    And creates a board named "Editable Board" with color "00ff00"
    When user "admin" on "LOCAL" shares the board with federated user "admin"
      | permissionEdit | 1 |
    Then the OCS response should have status code "200"
    And user "admin" on "REMOTE" should see the board "Editable Board"
