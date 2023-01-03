Feature: Sessions

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    Given acting as user "user0"
    And creates a board named "Shared board" with color "fafafa"
    And shares the board with user "user1"


  Scenario: Open a board with multiple users
    Given acting as user "user0"
    And user opens the board named "Shared board"
    When fetches the board named "Shared board"
    Then the response should have a status code "200"
    And the response should have a list of active sessions with the length 1
    And the user "user0" should be in the list of active sessions

    Given acting as user "user1"
    And user opens the board named "Shared board"
    When fetches the board named "Shared board"
    Then the response should have a status code "200"
    And the response should have a list of active sessions with the length 2
    And the user "user0" should be in the list of active sessions
    And the user "user1" should be in the list of active sessions

    When user closes the board named "Shared board"
    And fetches the board named "Shared board"
    Then the response should have a status code "200"
    And the response should have a list of active sessions with the length 1
    And the user "user0" should be in the list of active sessions
    
