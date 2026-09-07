Feature: REST API - Labels
  Basic coverage of the label endpoints documented at
  https://deck.readthedocs.io/en/stable/API/#labels

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"
    And sending "POST" to the API endpoint "/boards" with body:
      | title | Label board |
      | color | ff0000      |
    And the response value "id" is stored as "boardId"

  Scenario: GET /boards/{boardId}/labels/{labelId} - Get label details
    Given the response value "labels.0.id" is stored as "labelId"
    And the response value "labels.0.title" is stored as "labelTitle"
    When sending "GET" to the API endpoint "/boards/<boardId>/labels/<labelId>"
    Then the response should have a status code "200"
    And the response value "id" should be "<labelId>"
    And the response value "title" should be "<labelTitle>"
    And the response value "boardId" should be "<boardId>"
    And the response value "cardId" should be "null"

  Scenario: POST /boards/{boardId}/labels - Create a new label
    When sending "POST" to the API endpoint "/boards/<boardId>/labels" with body:
      | title | Blocked |
      | color | 31CC7C  |
    Then the response should have a status code "200"
    And the response value "title" should be "Blocked"
    And the response value "color" should be "31CC7C"
    And the response value "boardId" should be "<boardId>"
    And the response value "id" is stored as "labelId"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response value "labels" should have 5 entries

  Scenario: POST /boards/{boardId}/labels - Fail to create a label with an invalid title
    When sending "POST" to the API endpoint "/boards/<boardId>/labels" with body:
      | title |        |
      | color | 31CC7C |
    Then the response should have a status code "400"
    And the response value "status" should be "400"

  Scenario: PUT /boards/{boardId}/labels/{labelId} - Update label details
    Given sending "POST" to the API endpoint "/boards/<boardId>/labels" with body:
      | title | Label to update |
      | color | 31CC7C          |
    And the response value "id" is stored as "labelId"
    When sending "PUT" to the API endpoint "/boards/<boardId>/labels/<labelId>" with body:
      | title | Updated label |
      | color | 317CCC        |
    Then the response should have a status code "200"
    And the response value "title" should be "Updated label"
    And the response value "color" should be "317CCC"
    When sending "GET" to the API endpoint "/boards/<boardId>/labels/<labelId>"
    Then the response value "title" should be "Updated label"
    And the response value "color" should be "317CCC"

  Scenario: DELETE /boards/{boardId}/labels/{labelId} - Delete a label
    Given sending "POST" to the API endpoint "/boards/<boardId>/labels" with body:
      | title | Label to delete |
      | color | 31CC7C          |
    And the response value "id" is stored as "labelId"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/labels/<labelId>"
    Then the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/labels/<labelId>"
    Then the response should have a status code "403"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response value "labels" should have 4 entries

  Scenario: Labels of a board of another user are not accessible
    Given the response value "labels.0.id" is stored as "labelId"
    And acting as user "user1"
    When sending "GET" to the API endpoint "/boards/<boardId>/labels/<labelId>"
    Then the response should have a status code "403"
    And the response value "message" should be "Permission denied"
    When sending "POST" to the API endpoint "/boards/<boardId>/labels" with body:
      | title | Sneaky label |
      | color | 31CC7C       |
    Then the response should have a status code "403"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/labels/<labelId>"
    Then the response should have a status code "403"
