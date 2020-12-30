Feature: File sharing

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    #And user "user2" exists
    #Given group "group0" exists
    #And group "group1" exists
    #Given user "user1" belongs to group "group1"

  Scenario: Share a file with a card
    Given Logging in using web as "admin"
    And creates a board named "Shared board" with color "fafafa"
    And shares the board with user "user1"
    Then the HTTP status code should be "200"
    Given Logging in using web as "user1"
    And create a stack named "Stack"
    And create a card named "Test"
