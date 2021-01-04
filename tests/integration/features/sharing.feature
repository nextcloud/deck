Feature: File sharing

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user3" exists
    Given group "group0" exists
    And group "group1" exists
    Given user "user2" belongs to group "group1"
    Given user "user3" belongs to group "group1"

  Scenario: Share a file with a card by the board owner
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And create a stack named "Stack"
    And create a card named "Test"
    And shares the board with user "user1"
    Then the HTTP status code should be "200"

    Given using new dav path
    When User "user0" uploads file "../data/test.txt" to "/user0-file.txt"
    Then the HTTP status code should be "201"
    Given acting as user "user0"
    When share the file "/user0-file.txt" with the card
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

    And as "user1" the file "/Deck/user0-file.txt" exists

  Scenario: Share a file with a card by another user
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And create a stack named "Stack"
    And create a card named "Test"
    And shares the board with user "user1"
      | permissionEdit | 1 |
      | permissionShare | 1 |
      | permissionManage | 1 |
    Then the HTTP status code should be "200"

    Given using new dav path
    When User "user1" uploads file "../data/test.txt" to "/user1-file.txt"
    Then the HTTP status code should be "201"
    Given acting as user "user1"
    And share the file "/user1-file.txt" with the card
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

    And as "user0" the file "/Deck/user1-file.txt" exists
    And as "user1" the file "/Deck/user1-file.txt" does not exist

  Scenario: Share a file with a card by another user fails without edit permission
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And create a stack named "Stack"
    And create a card named "Test"
    And shares the board with user "user1"
    Then the HTTP status code should be "200"

    Given using new dav path
    When User "user1" uploads file "../data/test.txt" to "/user1-file.txt"
    Then the HTTP status code should be "201"
    Given acting as user "user1"
    And share the file "/user1-file.txt" with the card
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    And as "user0" the file "/Deck/user1-file.txt" does not exist

  Scenario: Share a file with a card by another user through a group
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And create a stack named "Stack"
    And create a card named "Test"
    And shares the board with group "group1"
    Then the HTTP status code should be "200"

    Given using new dav path
    When User "user0" uploads file "../data/test.txt" to "/user0-file2.txt"
    Then the HTTP status code should be "201"
    Given acting as user "user0"
    When share the file "/user0-file2.txt" with the card
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

    And as "user2" the file "/Deck/user0-file2.txt" exists
    And as "user0" the file "/Deck/user0-file2.txt" does not exist

  Scenario: Remove incoming group share as a user
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And create a stack named "Stack"
    And create a card named "Test"
    And shares the board with group "group1"
    Then the HTTP status code should be "200"

    Given using new dav path
    When User "user0" uploads file "../data/test.txt" to "/user0-file2.txt"
    Then the HTTP status code should be "201"
    Given acting as user "user0"
    When share the file "/user0-file2.txt" with the card
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

    And as "user2" the file "/Deck/user0-file2.txt" exists
    And as "user3" the file "/Deck/user0-file2.txt" exists
    And as "user0" the file "/Deck/user0-file2.txt" does not exist

    Given User "user2" deletes file "/Deck/user0-file2.txt"
    And as "user2" the file "/Deck/user0-file2.txt" does not exist
    And as "user3" the file "/Deck/user0-file2.txt" exists

  Scenario: Remove a share as the owner
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And create a stack named "Stack"
    And create a card named "Test"
    And shares the board with group "group1"
    Then the HTTP status code should be "200"

    Given using new dav path
    When User "user0" uploads file "../data/test.txt" to "/user0-file2.txt"
    Then the HTTP status code should be "201"
    Given acting as user "user0"
    When share the file "/user0-file2.txt" with the card
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

    And as "user2" the file "/Deck/user0-file2.txt" exists
    And as "user3" the file "/Deck/user0-file2.txt" exists
    And as "user0" the file "/Deck/user0-file2.txt" does not exist

    Given acting as user "user0"
    When Deleting last share
    And as "user2" the file "/Deck/user0-file2.txt" does not exist
    And as "user3" the file "/Deck/user0-file2.txt" does not exist
