Feature: REST API - Stacks
  Basic coverage of the stack endpoints documented at
  https://deck.readthedocs.io/en/stable/API/#stacks

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"
    And sending "POST" to the API endpoint "/boards" with body:
      | title | Stack board |
      | color | ff0000      |
    And the response value "id" is stored as "boardId"

  Scenario: POST /boards/{boardId}/stacks - Create a new stack
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    Then the response should have a status code "200"
    And the response value "title" should be "ToDo"
    And the response value "order" should be "1"
    And the response value "boardId" should be "<boardId>"
    And the response value "deletedAt" should be "0"

  Scenario: POST /boards/{boardId}/stacks - Fail to create a stack with an invalid title
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | This is a very long name that exceeds the maximum length of a deck stack which is limited to 100 characters |
      | order | 1                                                                                                           |
    Then the response should have a status code "400"
    And the response value "status" should be "400"

  Scenario: GET /boards/{boardId}/stacks - Get the stacks of a board
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | Doing |
      | order | 2     |
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks"
    Then the response should have a status code "200"
    And the response should be a list of objects
    And the response list should contain 2 entries
    And the response list should contain an entry with "title" set to "ToDo"
    And the response list should contain an entry with "title" set to "Doing"

  Scenario: GET /boards/{boardId}/stacks/{stackId} - Get stack details
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And the response value "id" is stored as "stackId"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | A card in the stack |
      | type  | plain               |
      | order | 999                 |
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>"
    Then the response should have a status code "200"
    And the response value "id" should be "<stackId>"
    And the response value "title" should be "ToDo"
    And the response value "boardId" should be "<boardId>"
    And the response value "cards" should have 1 entry
    And the response value "cards.0.title" should be "A card in the stack"
    And the response should have the header "ETag"

  Scenario: PUT /boards/{boardId}/stacks/{stackId} - Update stack details
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And the response value "id" is stored as "stackId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>" with body:
      | title | Done |
      | order | 5    |
    Then the response should have a status code "200"
    And the response value "title" should be "Done"
    And the response value "order" should be "5"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>"
    Then the response value "title" should be "Done"
    And the response value "order" should be "5"

  Scenario: DELETE /boards/{boardId}/stacks/{stackId} - Delete a stack
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | Stack to delete |
      | order | 1               |
    And the response value "id" is stored as "stackId"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/stacks/<stackId>"
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks"
    Then the response should have a status code "200"
    And the response list should contain 0 entries

  Scenario: GET /boards/{boardId}/stacks/archived - Get the archived cards grouped by stack
    Given sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And the response value "id" is stored as "stackId"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Active card |
      | type  | plain       |
      | order | 1           |
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Archived card |
      | type  | plain         |
      | order | 2             |
    And the response value "id" is stored as "cardId"
    And sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/archive"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/archived"
    Then the response should have a status code "200"
    And the response should be a list of objects
    And the response list should contain 1 entry
    # The endpoint lists the stacks of the board, each one holding its archived cards only
    And the response value "0.title" should be "ToDo"
    And the response value "0.cards" should have 1 entry
    And the response value "0.cards.0.title" should be "Archived card"

  Scenario: Stacks of a board of another user are not accessible
    Given acting as user "user1"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks"
    Then the response should have a status code "403"
    And the response value "message" should be "Permission denied"
    When sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | Sneaky stack |
      | order | 1            |
    Then the response should have a status code "403"
