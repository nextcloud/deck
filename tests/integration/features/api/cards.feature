Feature: REST API - Cards
  Basic coverage of the card endpoints documented at
  https://deck.readthedocs.io/en/stable/API/#cards

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"
    And sending "POST" to the API endpoint "/boards" with body:
      | title | Card board |
      | color | ff0000     |
    And the response value "id" is stored as "boardId"
    And the response value "labels.0.id" is stored as "labelId"
    And the response value "labels.0.title" is stored as "labelTitle"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And the response value "id" is stored as "stackId"

  Scenario: POST /boards/{boardId}/stacks/{stackId}/cards - Create a new card
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title       | Test card        |
      | type        | plain            |
      | order       | 999              |
      | description | A description    |
    Then the response should have a status code "200"
    And the response value "title" should be "Test card"
    And the response value "description" should be "A description"
    And the response value "type" should be "plain"
    And the response value "order" should be "999"
    And the response value "stackId" should be "<stackId>"
    And the response value "owner.uid" should be "user0"
    And the response value "archived" should be "false"
    And the response value "deletedAt" should be "0"
    And the response value "done" should be "null"

  Scenario: POST /boards/{boardId}/stacks/{stackId}/cards - Create a card with a duedate
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title   | Card with duedate         |
      | type    | plain                     |
      | order   | 999                       |
      | duedate | 2019-12-24T19:29:30+00:00 |
    Then the response should have a status code "200"
    And the response value "duedate" should be "2019-12-24T19:29:30+00:00"

  Scenario: POST /boards/{boardId}/stacks/{stackId}/cards - Fail to create a card with an invalid title
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | This is a very long name that exceeds the maximum length of a deck card which is limited to 255 characters. This is a very long name that exceeds the maximum length of a deck card which is limited to 255 characters. This is a very long name that exceeds the maximum length of a deck card. |
      | type  | plain                                                                                                                                                                                                                                                                                          |
      | order | 999                                                                                                                                                                                                                                                                                            |
    Then the response should have a status code "400"
    And the response value "status" should be "400"

  Scenario: GET /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Get card details
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card to read |
      | type  | plain        |
      | order | 999          |
    And the response value "id" is stored as "cardId"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response should have a status code "200"
    And the response value "id" should be "<cardId>"
    And the response value "title" should be "Card to read"
    And the response value "stackId" should be "<stackId>"
    And the response should have the header "ETag"

  Scenario: PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Update card details
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card to update |
      | type  | plain          |
      | order | 999            |
    And the response value "id" is stored as "cardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>" with body:
      | title       | Updated card              |
      | description | An updated description    |
      | type        | plain                     |
      | owner       | user0                     |
      | order       | 5                         |
      | duedate     | 2019-12-24T19:29:30+00:00 |
    Then the response should have a status code "200"
    And the response value "title" should be "Updated card"
    And the response value "description" should be "An updated description"
    And the response value "order" should be "5"
    And the response value "duedate" should be "2019-12-24T19:29:30+00:00"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "title" should be "Updated card"
    And the response value "description" should be "An updated description"

  Scenario: PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Mark a card as done
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card to finish |
      | type  | plain          |
      | order | 999            |
    And the response value "id" is stored as "cardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>" with body:
      | title | Card to finish            |
      | type  | plain                     |
      | owner | user0                     |
      | done  | 2026-01-02T10:00:00+00:00 |
    Then the response should have a status code "200"
    And the response value "done" should be "2026-01-02T10:00:00+00:00"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>" with body:
      | title | Card to finish |
      | type  | plain          |
      | owner | user0          |
      | done  | null           |
    Then the response should have a status code "200"
    And the response value "done" should be "null"

  Scenario: PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/archive - Archive and unarchive a card
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card to archive |
      | type  | plain           |
      | order | 999             |
    And the response value "id" is stored as "cardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/archive"
    Then the response should have a status code "200"
    And the response value "archived" should be "true"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "archived" should be "true"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/unarchive"
    Then the response should have a status code "200"
    And the response value "archived" should be "false"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "archived" should be "false"

  Scenario: PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignLabel - Assign and remove a label
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card with a label |
      | type  | plain             |
      | order | 999               |
    And the response value "id" is stored as "cardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/assignLabel" with body:
      | labelId | <labelId> |
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "labels" should have 1 entry
    And the response value "labels.0.id" should be "<labelId>"
    And the response value "labels.0.title" should be "<labelTitle>"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/removeLabel" with body:
      | labelId | <labelId> |
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "labels" should be empty

  Scenario: PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignUser - Assign and unassign a user
    Given sending "POST" to the API endpoint "/boards/<boardId>/acl" with body:
      | type             | 0     |
      | participant      | user1 |
      | permissionEdit   | true  |
      | permissionShare  | false |
      | permissionManage | false |
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card with an assignee |
      | type  | plain                 |
      | order | 999                   |
    And the response value "id" is stored as "cardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/assignUser" with body:
      | userId | user1 |
    Then the response should have a status code "200"
    And the response value "participant.uid" should be "user1"
    And the response value "cardId" should be "<cardId>"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "assignedUsers" should have 1 entry
    And the response value "assignedUsers.0.participant.uid" should be "user1"

    # Assigning the same user twice is rejected with a bad request
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/assignUser" with body:
      | userId | user1 |
    Then the response should have a status code "400"

    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/unassignUser" with body:
      | userId | user1 |
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response value "assignedUsers" should be empty

  Scenario: PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/reorder - Change the order of a card
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | First card |
      | type  | plain      |
      | order | 0          |
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Second card |
      | type  | plain       |
      | order | 1           |
    And the response value "id" is stored as "cardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/reorder" with body:
      | stackId | <stackId> |
      | order   | 0         |
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response should have a status code "200"
    And the response value "order" should be "0"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>"
    Then the response value "cards.0.title" should be "Second card"
    And the response value "cards.1.title" should be "First card"

  Scenario: DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Delete a card
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card to delete |
      | type  | plain          |
      | order | 999            |
    And the response value "id" is stored as "cardId"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response should have a status code "403"

  Scenario: Cards of a board of another user are not accessible
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Private card |
      | type  | plain        |
      | order | 999          |
    And the response value "id" is stored as "cardId"
    Given acting as user "user1"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response should have a status code "403"
    And the response value "message" should be "Permission denied"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>"
    Then the response should have a status code "403"
