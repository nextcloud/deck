Feature: OCS API - Config
  Basic coverage of the config endpoints documented at
  https://deck.readthedocs.io/en/stable/API/#config

  Background:
    Given user "admin" exists
    And user "user0" exists
    And user "user1" exists
    And acting as user "user0"

  Scenario: GET /api/v1.0/config - Fetch app configuration values
    When sending "GET" to the OCS API endpoint "/config"
    Then the response should have a status code "200"
    And the response value "ocs.meta.statuscode" should be "200"
    And the response should contain the key "ocs.data.calendar"
    And the response should contain the key "ocs.data.cardDetailsInModal"
    And the response should contain the key "ocs.data.cardIdBadge"
    And the response should contain the key "ocs.data.stackAddCardAtTop"
    And the response value "ocs.data.stackAddCardAtTop" should be "false"

  Scenario: GET /api/v1.0/config - The group limit is only exposed to administrators
    When sending "GET" to the OCS API endpoint "/config"
    Then the response should have a status code "200"
    And the response should not contain the key "ocs.data.groupLimit"
    Given acting as user "admin"
    When sending "GET" to the OCS API endpoint "/config"
    Then the response should have a status code "200"
    And the response should contain the key "ocs.data.groupLimit"

  Scenario: POST /api/v1.0/config/{key} - Set a user config value
    When sending "POST" to the OCS API endpoint "/config/calendar" with body:
      | value | false |
    Then the response should have a status code "200"
    And the response value "ocs.data" should be "false"
    When sending "GET" to the OCS API endpoint "/config"
    Then the response value "ocs.data.calendar" should be "false"
    When sending "POST" to the OCS API endpoint "/config/calendar" with body:
      | value | true |
    Then the response should have a status code "200"
    And the response value "ocs.data" should be "true"
    When sending "GET" to the OCS API endpoint "/config"
    Then the response value "ocs.data.calendar" should be "true"

  Scenario: POST /api/v1.0/config/{key} - Set the new card position
    When sending "POST" to the OCS API endpoint "/config/stackAddCardAtTop" with body:
      | value | true |
    Then the response should have a status code "200"
    And the response value "ocs.data" should be "true"
    When sending "GET" to the OCS API endpoint "/config"
    Then the response value "ocs.data.stackAddCardAtTop" should be "true"
    When sending "POST" to the OCS API endpoint "/config/stackAddCardAtTop" with body:
      | value | false |
    Then the response should have a status code "200"
    And the response value "ocs.data" should be "false"
    When sending "GET" to the OCS API endpoint "/config"
    Then the response value "ocs.data.stackAddCardAtTop" should be "false"

  Scenario: POST /api/v1.0/config/{key} - Set a board config value
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Config board |
      | color | ff0000       |
    And the response value "id" is stored as "boardId"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:notify-due" with body:
      | value | assigned |
    Then the response should have a status code "200"
    And the response value "ocs.data" should be "assigned"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response value "settings.notify-due" should be "assigned"

  Scenario: POST /api/v1.0/config/{key} - Board settings of other users cannot be changed
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Private config board |
      | color | ff0000               |
    And the response value "id" is stored as "boardId"
    Given acting as user "user1"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:notify-due" with body:
      | value | all |
    Then the response should have a status code "403"

  Scenario: POST /api/v1.0/config/{key} - Swimlane settings are shared with board members
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Swimlane board |
      | color | ff0000         |
    And the response value "id" is stored as "boardId"
    And sending "POST" to the API endpoint "/boards/<boardId>/acl" with body:
      | type             | 0     |
      | participant      | user1 |
      | permissionEdit   | false |
      | permissionShare  | false |
      | permissionManage | false |
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:swimlaneMode" with body:
      | value | labels |
    Then the response should have a status code "200"
    And the response value "ocs.data" should be "labels"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:swimlaneLabelOrder" with body:
      | value | [2,1] |
    Then the response should have a status code "200"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:swimlaneMode" with body:
      | value | columns |
    Then the response should have a status code "400"
    Given acting as user "user1"
    When sending "GET" to the API endpoint "/boards/<boardId>"
    Then the response value "settings.swimlaneMode" should be "labels"
    And the response value "settings.swimlaneLabelOrder" should be "[2,1]"
    And the response value "settings.swimlaneUserOrder" should be "[]"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:swimlaneMode" with body:
      | value | assignees |
    Then the response should have a status code "403"

  Scenario: POST /api/v1.0/config/{key} - Swimlane user order keeps a user and a group of the same id apart
    Given sending "POST" to the API endpoint "/boards" with body:
      | title | Swimlane order board |
      | color | ff0000               |
    And the response value "id" is stored as "boardId"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:swimlaneUserOrder" with body:
      | value | ["0:user1","1:user1"] |
    Then the response should have a status code "200"
    When sending "POST" to the OCS API endpoint "/config/board:<boardId>:swimlaneUserOrder" with body:
      | value | ["user1"] |
    Then the response should have a status code "400"
