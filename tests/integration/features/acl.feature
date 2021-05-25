Feature: acl
  Routes should check for permissions when a user sends a requests

  Background:
	Given user "admin" exists
	And user "user0" exists
	And user "user1" exists
	And user "user2" exists
	Given group "group0" exists
	And group "group1" exists
	Given user "user1" is member of group "group1"

  Scenario: Fetch the board list
	Given Using web as user "user0"
	When fetching the board list
	Then the HTTP status code should be "200"
	And the response Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of owned board
	Given Using web as user "admin"
	And creates a board named "MyPrivateAdminBoard" with color "fafafa"
	When fetches the board named "MyPrivateAdminBoard"
	Then the HTTP status code should be "200"
	And the response Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of an other users board
	Given Using web as user "admin"
	And creates a board named "MyPrivateAdminBoard" with color "ff0000"
	Given Using web as user "user0"
	When fetches the board named "MyPrivateAdminBoard"
	Then the HTTP status code should be "403"
	And the response Content-Type should be "application/json; charset=utf-8"

	Scenario: Share a board
		Given Using web as user "user0"
		And creates a board named "Shared board" with color "ff0000"
		And shares the board with user "user1"
			| permissionEdit   | 0 |
			| permissionShare  | 0 |
			| permissionManage | 0 |
		And the HTTP status code should be 200
		And shares the board with user "user2"
			| permissionEdit   | 1 |
			| permissionShare  | 1 |
			| permissionManage | 1 |
		And the HTTP status code should be 200

		Given Using web as user "user2"
		When fetches the board named "Shared board"
		Then the current user should have "read" permissions on the board
		And the current user should have "edit" permissions on the board
		And the current user should have "share" permissions on the board
		And the current user should have "manage" permissions on the board
		And create a stack named "Stack"
		And the HTTP status code should be 200
		And create a card named "Test"
		And the HTTP status code should be 200


		Given Using web as user "user1"
		When fetches the board named "Shared board"
		And create a card named "Test"
		And the HTTP status code should be 403
		Then the current user should have "read" permissions on the board
		And the current user should not have "edit" permissions on the board
		And the current user should not have "share" permissions on the board
		And the current user should not have "manage" permissions on the board
		And create a stack named "Stack"
		And the HTTP status code should be 403


	Scenario: Reshare a board
		Given Using web as user "user0"
		And creates a board named "Reshared board" with color "ff0000"
		And shares the board with user "user1"
			| permissionEdit   | 0 |
			| permissionShare  | 1 |
			| permissionManage | 0 |
		And the HTTP status code should be 200
		Given Using web as user "user1"
		When fetches the board named "Shared board"
		And shares the board with user "user2"
			| permissionEdit   | 1 |
			| permissionShare  | 1 |
			| permissionManage | 1 |
		And the HTTP status code should be 200
		Given Using web as user "user2"
		When fetches the board named "Shared board"
		Then the current user should have "read" permissions on the board
		And the current user should not have "edit" permissions on the board
		And the current user should have "share" permissions on the board
		And the current user should not have "manage" permissions on the board
