Feature: done-column
  A board column can be designated as the "done column".
  Cards moved into it are automatically marked as done (VTODO STATUS:COMPLETED).
  Cards moved out revert to not-done, but only while the done column is active.
  Marking a card as done from any other column automatically moves it to the done column.

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists

  Scenario: User without manage permission cannot set the done column
    Given acting as user "user0"
    And creates a board named "Done Column Permission Test" with color "ff0000"
    And create a stack named "Done"
    And shares the board with user "user1"
      | permissionEdit   | 1 |
      | permissionShare  | 0 |
      | permissionManage | 0 |
    Given acting as user "user1"
    And fetches the board named "Done Column Permission Test"
    When sets the current stack as done column
    Then the response should have a status code 403

  Scenario: Archived board cannot have a done column set
    Given acting as user "user0"
    And creates a board named "Archived Board Done Column" with color "ff0000"
    And create a stack named "Done"
    And the board is archived
    When sets the current stack as done column
    Then the response should have a status code 403

  Scenario: Done column lifecycle — marking, reverting, manual done, and unsetting
    Given acting as user "user0"
    And creates a board named "Done Column Board" with color "ff0000"
    And create a stack named "ToDo"
    And create a card named "My Task"
    And remember the last stack as "todo-stack"
    And create a stack named "Done"
    And remember the last stack as "done-stack"

    # Owner can set a done column; the stack is flagged as the done column
    When sets the current stack as done column
    Then the response should have a status code 200
    And the current stack should be marked as done column

    # Moving a card into the done column marks it as done
    When move the card to the stack "done-stack"
    Then the card should be marked as done

    # Moving a card out of the done column reverts it to not-done
    When move the card to the stack "todo-stack"
    Then the card should not be marked as done

    # Manually marking a card as done and then moving it into the done column keeps it done
    When mark the card as done
    And move the card to the stack "done-stack"
    Then the card should be marked as done

    # Owner can unset the done column; the flag is removed from the stack
    When unsets the current stack as done column
    Then the response should have a status code 200
    And the current stack should not be marked as done column

    # After unsetting, moving a card out of the former done stack does not change its done status
    When move the card to the stack "todo-stack"
    Then the card should be marked as done

  Scenario: Setting a stack as done column marks all existing cards in it as done
    Given acting as user "user0"
    And creates a board named "Bulk Done Board" with color "ff0000"
    And create a stack named "ToDo"
    And create a stack named "Done"
    And create a card named "Pre-existing Task"
    And remember the last card as "pre-existing"

    # Setting the stack as done column should mark the pre-existing card as done
    When sets the current stack as done column
    Then the response should have a status code 200
    And the remembered card "pre-existing" should be marked as done

  Scenario: Marking a card as done automatically moves it to the done column
    Given acting as user "user0"
    And creates a board named "Auto Move Done Board" with color "ff0000"
    And create a stack named "ToDo"
    And create a card named "Task A"
    And remember the last stack as "todo-stack"
    And create a stack named "Done"
    And remember the last stack as "done-stack"

    # Set the Done stack as the done column
    When sets the current stack as done column
    Then the response should have a status code 200
    And the current stack should be marked as done column

    # Mark the card as done from the ToDo stack — it should auto-move to the done column
    When mark the card as done
    Then the card should be marked as done
    And the card should be in the stack "done-stack"
