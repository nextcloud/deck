Feature: REST API - Boards
  Basic coverage of the board endpoints documented at
  https://deck.readthedocs.io/en/stable/API/#boards

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"

  Scenario: POST /boards - Create a new board
    When sending "POST" to the API endpoint "/boards" with body:
      | title | Board title |
      | color | ff0000      |
    Then the response should have a status code "200"
    And the response value "title" should be "Board title"
    And the response value "color" should be "ff0000"
    And the response value "archived" should be "false"
    And the response value "deletedAt" should be "0"
    And the response value "owner.uid" should be "user0"
    And the response value "permissions.PERMISSION_READ" should be "true"
    And the response value "permissions.PERMISSION_EDIT" should be "true"
    And the response value "permissions.PERMISSION_MANAGE" should be "true"
    And the response value "permissions.PERMISSION_SHARE" should be "true"
    And the response value "acl" should be empty
    And the response value "labels" should have 4 entries

  Scenario: POST /boards - Fail to create a board with invalid parameters
    When sending "POST" to the API endpoint "/boards" with body:
      | title | This is a very long name that exceeds the maximum length of a deck board which is limited to 100 characters |
      | color | ff0000                                                                                                      |
    Then the response should have a status code "400"
    And the response value "status" should be "400"
    When sending "POST" to the API endpoint "/boards" with body:
      | title | Board title |
      | color |             |
    Then the response should have a status code "400"
    And the response value "status" should be "400"

  Scenario: GET /boards - Get a list of boards
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Listed board |
      | color | 00ff00       |
    And the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards"
    Then the response should have a status code "200"
    And the response should be a list of objects
    And the response list should contain an entry with "title" set to "Listed board"
    And the response should have the header "ETag"

  Scenario: GET /boards - Get a list of boards with details
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Detailed board |
      | color | 00ff00         |
    And the response value "id" is stored as "boardId"
    When sending "GET" to the API endpoint "/boards?details=true"
    Then the response should have a status code "200"
    And the response list should contain an entry with "id" set to "<boardId>"

  Scenario: GET /boards - Limit the board list with If-Modified-Since
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Unmodified board |
      | color | 00ff00           |
    When sending "GET" to the API endpoint "/boards" with the header "If-Modified-Since" set to "Sun, 03 Aug 2036 10:34:12 GMT"
    Then the response should have a status code "200"
    And the response list should contain 0 entries

  Scenario: GET /boards/{boardId} - Get board details
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Board details |
      | color | 0000ff        |
    And the response value "id" is stored as "boardId"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response should have a status code "200"
    And the response value "id" should be "<boardId>"
    And the response value "title" should be "Board details"
    And the response value "color" should be "0000ff"
    And the response should have the header "ETag"

  Scenario: GET /boards/{boardId} - Unchanged boards are answered with 304 Not Modified
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Etag board |
      | color | 0000ff     |
    And the response value "id" is stored as "boardId"
    And sending "GET" to the API endpoint "/boards/<boardId>"
    And the response header "ETag" is stored as "boardEtag"
    When sending "GET" to the API endpoint "/boards/<boardId>" with the header "If-None-Match" set to "<boardEtag>"
    Then the response should have a status code "304"

  Scenario: PUT /boards/{boardId} - Update board details
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Board to update |
      | color | ff0000          |
    And the response value "id" is stored as "boardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>" with body:
      | title    | Updated board |
      | color    | 00ff00        |
      | archived | true          |
    Then the response should have a status code "200"
    And the response value "title" should be "Updated board"

  Scenario: PUT /boards/{boardId} - Update board details fails if If-Match doesn't match
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Board to update |
      | color | ff0000          |
    And the response value "id" is stored as "boardId"
    When sending "PUT" to the API endpoint "/boards/<boardId>" with the header "If-Match" set to "invalid" and body:
      | title    | Updated board |
      | color    | 00ff00        |
      | archived | true          |
    Then the response should have a status code "412"

  Scenario: PUT /boards/{boardId} - Update board details succeeds if If-Match matches
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Board to update |
      | color | ff0000          |
    And the response value "id" is stored as "boardId"
    And sending "GET" to the API endpoint "/boards/<boardId>"
    And the response header "ETag" is stored as "boardEtag"
    When sending "PUT" to the API endpoint "/boards/<boardId>" with the header "If-Match" set to "<boardEtag>" and body:
      | title    | Updated board |
      | color    | 00ff00        |
      | archived | true          |
    Then the response should have a status code "200"
    And the response value "title" should be "Updated board"
    And the response value "color" should be "00ff00"
    And the response value "archived" should be "true"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response value "title" should be "Updated board"
    And the response value "archived" should be "true"

  Scenario: DELETE /boards/{boardId} - Delete a board and restore it again
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Board to delete |
      | color | ff0000          |
    And the response value "id" is stored as "boardId"
    When sending "DELETE" to the API endpoint "/boards/<boardId>"
    Then the response should have a status code "200"
    # Boards are deleted in two steps, so they stay listed with a deletion timestamp
    # until they are either restored or removed for good by the cleanup job
    And the response value "deletedAt" should not be "0"
    When sending "POST" to the API endpoint "/boards/<boardId>/undo_delete"
    Then the response should have a status code "200"
    And the response value "deletedAt" should be "0"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response should have a status code "200"
    And the response value "title" should be "Board to delete"
    And the response value "deletedAt" should be "0"

  Scenario: POST /boards/{boardId}/acl - Add, update and delete an acl rule
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Shared board |
      | color | ff0000       |
    And the response value "id" is stored as "boardId"
    When sending "POST" to the API endpoint "/boards/<boardId>/acl" with body:
      | type             | 0     |
      | participant      | user1 |
      | permissionEdit   | true  |
      | permissionShare  | false |
      | permissionManage | false |
    Then the response should have a status code "200"
    And the response value "participant.uid" should be "user1"
    And the response value "type" should be "0"
    And the response value "boardId" should be "<boardId>"
    And the response value "permissionEdit" should be "true"
    And the response value "permissionShare" should be "false"
    And the response value "permissionManage" should be "false"
    And the response value "owner" should be "false"
    And the response value "id" is stored as "aclId"

    When sending "PUT" to the API endpoint "/boards/<boardId>/acl/<aclId>" with body:
      | permissionEdit   | false |
      | permissionShare  | true  |
      | permissionManage | true  |
    Then the response should have a status code "200"
    And the response value "permissionEdit" should be "false"
    And the response value "permissionShare" should be "true"
    And the response value "permissionManage" should be "true"

    Given acting as user "user1"
    When sending "GET" to the API endpoint "/boards"
    Then the response list should contain an entry with "title" set to "Shared board"

    Given acting as user "user0"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/acl/<aclId>"
    Then the response should have a status code "200"
    Given acting as user "user1"
    When sending "GET" to the API endpoint "/boards"
    Then the response list should not contain an entry with "title" set to "Shared board"

  Scenario: POST /boards/{boardId}/clone - Clone a board
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Board to clone |
      | color | ff0000         |
    And the response value "id" is stored as "boardId"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And the response value "id" is stored as "stackId"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card to clone |
      | type  | plain         |
      | order | 999           |
    When sending "POST" to the API endpoint "/boards/<boardId>/clone" with body:
      | withCards | true |
    Then the response should have a status code "200"
    And the response value "title" should be "Board to clone (copy)"
    And the response value "id" should not be "<boardId>"
    And the response value "id" is stored as "clonedBoardId"
    When sending "GET" to the API endpoint "/boards/<clonedBoardId>/stacks"
    Then the response should have a status code "200"
    And the response list should contain 1 entry
    And the response value "0.title" should be "ToDo"
    And the response value "0.cards" should have 1 entry
    And the response value "0.cards.0.title" should be "Card to clone"

  Scenario: Boards of other users are not accessible
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Private board |
      | color | ff0000        |
    And the response value "id" is stored as "boardId"
    Given acting as user "user1"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response should have a status code "403"
    And the response value "status" should be "403"
    And the response value "message" should be "Permission denied"
    When sending "PUT" to the API endpoint "/boards/<boardId>" with body:
      | title    | Hijacked board |
      | color    | 00ff00         |
      | archived | false          |
    Then the response should have a status code "403"
    When sending "DELETE" to the API endpoint "/boards/<boardId>"
    Then the response should have a status code "403"
