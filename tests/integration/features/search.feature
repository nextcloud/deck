Feature: Searching for cards

  Background:
    Given user "admin" exists
    Given user "user0" exists
    Given Logging in using web as "admin"
    When creates a board named "MyBoard" with color "000000"
    When create a stack named "ToDo"
    And create a card named "Example task 1"
    And create a card named "Example task 2"
    When create a stack named "In progress"
    And create a card named "Progress task 1"
    And create a card named "Progress task 2"
    When create a stack named "Done"
    And create a card named "Done task 1"
    And set the description to "Done task description 1"
    And create a card named "Done task 2"
    And set the description to "Done task description 2"
    And shares the board with user "user0"


  Scenario: Search for a card with multiple terms
    When searching for "Example task"
    Then the card "Example task 1" is found
    Then the card "Example task 2" is found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found

  Scenario: Search for a card in a specific list
    When searching for "task list:Done"
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is found

  Scenario: Search for a card with one term
    When searching for "task"
    Then the card "Example task 1" is found
    Then the card "Example task 2" is found
    Then the card "Progress task 1" is found
    Then the card "Progress task 2" is found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is found

  Scenario: Search for a card with an differently cased term
    When searching for "tAsk"
    Then the card "Example task 1" is found
    Then the card "Example task 2" is found
    Then the card "Progress task 1" is found
    Then the card "Progress task 2" is found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is found

  Scenario: Search for a card title
    When searching for 'title:"Done task 1"'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is not found

  Scenario: Search for a card description
    When searching for 'description:"Done task description"'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is found

  Scenario: Search for a non-existing card description
    When searching for 'description:"Example"'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found

  Scenario: Search on shared boards
    Given Logging in using web as "user0"
    When searching for "task"
    Then the card "Example task 1" is found
    Then the card "Example task 2" is found
    Then the card "Progress task 1" is found
    Then the card "Progress task 2" is found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is found

  Scenario: Search for a card due date
    Given create a card named "Overdue task"
    And set the card attribute "duedate" to "2020-12-12"
    And create a card named "Future task"
    And set the card attribute "duedate" to "3000-12-12"
    And create a card named "Tomorrow task"
    And set the card duedate to "tomorrow"
    When searching for 'date:overdue'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found
    Then the card "Overdue task" is found
    Then the card "Future task" is not found

  Scenario: Search for a card due date
    And create a card named "Overdue task"
    And set the card attribute "duedate" to "2020-12-12"
    And create a card named "Future task"
    And set the card attribute "duedate" to "3000-12-12"
    And create a card named "Tomorrow task"
    And set the card duedate to "+12 hours"
    And create a card named "Next week task"
    And set the card duedate to "+5 days"

    When searching for 'date:today'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found
    Then the card "Overdue task" is not found
    Then the card "Future task" is not found
    Then the card "Tomorrow task" is found
    Then the card "Next week task" is not found

    When searching for 'date:week'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found
    Then the card "Overdue task" is not found
    Then the card "Future task" is not found
    Then the card "Tomorrow task" is found
    Then the card "Next week task" is found

    When searching for 'date:month'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found
    Then the card "Overdue task" is not found
    Then the card "Future task" is not found
    Then the card "Tomorrow task" is found
    Then the card "Next week task" is found

    When searching for 'date:none'
    Then the card "Example task 1" is found
    Then the card "Example task 2" is found
    Then the card "Progress task 1" is found
    Then the card "Progress task 2" is found
    Then the card "Done task 1" is found
    Then the card "Done task 2" is found
    Then the card "Overdue task" is not found
    Then the card "Future task" is not found
    Then the card "Tomorrow task" is not found
    Then the card "Next week task" is not found

    When searching for 'date:<"+7 days"'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found
    Then the card "Overdue task" is found
    Then the card "Future task" is not found
    Then the card "Tomorrow task" is found
    Then the card "Next week task" is found

    When searching for 'date:>"+10 days"'
    Then the card "Example task 1" is not found
    Then the card "Example task 2" is not found
    Then the card "Progress task 1" is not found
    Then the card "Progress task 2" is not found
    Then the card "Done task 1" is not found
    Then the card "Done task 2" is not found
    Then the card "Overdue task" is not found
    Then the card "Future task" is found
    Then the card "Tomorrow task" is not found
    Then the card "Next week task" is not found

  Scenario: Search for assigned user
    Given user "user1" exists
    And shares the board with user "user1"
    Given create a card named "Assigned card to user1"
    And assign the card to the user "user1"
    When searching for 'assigned:user1'
    Then the card "Example task 1" is not found
    And the card "Assigned card to user1" is found

  Scenario: Search for assigned user by displayname
    Given user "ada" with displayname "Ada Lovelace" exists
    And shares the board with user "ada"
    Given create a card named "Assigned card to ada"
    And assign the card to the user "ada"
    When searching for 'assigned:"Ada Lovelace"'
    Then the card "Example task 1" is not found
    And the card "Assigned card to ada" is found

  Scenario: Search for assigned users
    Given user "user1" exists
    And shares the board with user "user1"
    Given create a card named "Assigned card to user0"
    And assign the card to the user "user0"
    Given create a card named "Assigned card to user01"
    And assign the card to the user "user0"
    And assign the card to the user "user1"
    When searching for 'assigned:user0 assigned:user1'
    Then the card "Example task 1" is not found
    And the card "Assigned card to user0" is not found
    And the card "Assigned card to user01" is found

  Scenario: Search for assigned group
    Given user "user1" exists
    And shares the board with user "user1"
    Given group "group1" exists
    And shares the board with group "group1"
    Given user "user1" belongs to group "group1"
    Given create a card named "Assigned card to group1"
    And assign the card to the group "group1"
    When searching for 'assigned:user1'
    Then the card "Example task 1" is not found
    And the card "Assigned card to group1" is found

    When searching for 'assigned:group1'
    Then the card "Example task 1" is not found
    And the card "Assigned card to group1" is found

  Scenario: Search for assigned tag
    Given create a card named "Labeled card"
    # Default labels from boards are used for this test case
    And assign the tag "Finished" to the card
    When searching for 'tag:Finished'
    Then the card "Example task 1" is not found
    And the card "Labeled card" is found

    Given create a card named "Multi labeled card"
    And assign the tag "Finished" to the card
    And assign the tag "To review" to the card
    When searching for 'tag:Finished tag:Later'
    Then the card "Example task 1" is not found
    And the card "Multi labeled card" is not found

    When searching for 'tag:Finished tag:"To review"'
    Then the card "Example task 1" is not found
    And the card "Labeled card" is not found
    And the card "Multi labeled card" is found

  Scenario: Search for a card comment
    Given create a card named "Card with comment"
    And post a comment with content "My first comment" on the card
    When searching for "My first comment" in comments in unified search
    Then the comment with "My first comment" is found
    Then the comment with "Any other" is not found
