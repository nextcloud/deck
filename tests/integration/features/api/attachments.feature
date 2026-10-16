Feature: REST API - Attachments
  Basic coverage of the attachment endpoints documented at
  https://deck.readthedocs.io/en/stable/API/#attachments

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"
    And sending "POST" to the API endpoint "/boards" with body:
      | title | Attachment board |
      | color | ff0000           |
    And the response value "id" is stored as "boardId"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks" with body:
      | title | ToDo |
      | order | 1    |
    And the response value "id" is stored as "stackId"
    And sending "POST" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards" with body:
      | title | Card with attachments |
      | type  | plain                 |
      | order | 999                   |
    And the response value "id" is stored as "cardId"

  Scenario: POST /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Upload an attachment
    When uploading the file "test.txt" with content "Example content" as attachment type "deck_file" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response should have a status code "200"
    And the response value "cardId" should be "<cardId>"
    And the response value "type" should be "deck_file"
    And the response value "data" should be "test.txt"
    And the response value "createdBy" should be "user0"
    And the response value "deletedAt" should be "0"
    And the response value "extendedData.filesize" should be "15"

  Scenario: GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Get a list of attachments
    Given uploading the file "test.txt" with content "Example content" as attachment type "deck_file" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    And the response should have a status code "200"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response should have a status code "200"
    And the response should be a list of objects
    And the response list should contain 1 entry
    And the response list should contain an entry with "data" set to "test.txt"
    And the response value "0.extendedData.info.filename" should be "test"
    And the response value "0.extendedData.info.extension" should be "txt"

  Scenario: GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Get the attachment file
    Given uploading the file "test.txt" with content "Example content" as attachment type "deck_file" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    And the response value "id" is stored as "attachmentId"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments/<attachmentId>"
    Then the response should have a status code "200"
    And the response body should be "Example content"

  Scenario: DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Delete and restore an attachment
    Given uploading the file "test.txt" with content "Example content" as attachment type "deck_file" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    And the response value "id" is stored as "attachmentId"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments/<attachmentId>"
    Then the response should have a status code "200"
    And the response value "deletedAt" should not be "0"
    # Attachments are deleted in two steps, so they stay listed with a deletion
    # timestamp until they are either restored or removed for good
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response list should contain 1 entry
    And the response value "0.deletedAt" should not be "0"
    When sending "PUT" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments/<attachmentId>/restore"
    Then the response should have a status code "200"
    And the response value "deletedAt" should be "0"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response list should contain 1 entry
    And the response value "0.deletedAt" should be "0"

  Scenario: Attachments stored in the files app are only returned by API version 1.1
    Given using the Deck API version "1.1"
    When uploading the file "in-files.txt" with content "Example content" as attachment type "file" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response should have a status code "200"
    And the response value "type" should be "file"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response should have a status code "200"
    And the response list should contain 1 entry
    And the response list should contain an entry with "type" set to "file"
    # API version 1.0 predates the files app integration and only knows deck_file attachments
    Given using the Deck API version "1.0"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response should have a status code "200"
    And the response list should contain 0 entries

  Scenario: Attachments of a board of another user are not accessible
    Given uploading the file "test.txt" with content "Example content" as attachment type "deck_file" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    And the response value "id" is stored as "attachmentId"
    And acting as user "user1"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments"
    Then the response should have a status code "403"
    When sending "GET" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments/<attachmentId>"
    Then the response should have a status code "403"
    When sending "DELETE" to the API endpoint "/boards/<boardId>/stacks/<stackId>/cards/<cardId>/attachments/<attachmentId>"
    Then the response should have a status code "403"
