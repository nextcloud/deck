Feature: acl
  Routes should check for permissions when a user sends a requests

  Background:
	Given user "admin" exists
	And user "user0" exists
	And user "user1" exists
	And user "user2" exists
	Given group "group0" exists
	And group "group1" exists
	Given user "user1" belongs to group "group1"

  Scenario: Fetch the board list
	Given Logging in using web as "user0"
	When fetching the board list
	Then the response should have a status code "200"
	And the response Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of owned board
	Given Logging in using web as "admin"
	And creates a board named "MyPrivateAdminBoard" with color "fafafa"
	When fetches the board named "MyPrivateAdminBoard"
	Then the response should have a status code "200"
	And the response Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of an other users board
	Given Logging in using web as "admin"
	And creates a board named "MyPrivateAdminBoard" with color "ff0000"
	Given Logging in using web as "user0"
	When fetches the board named "MyPrivateAdminBoard"
	Then the response should have a status code "403"
	And the response Content-Type should be "application/json; charset=utf-8"

	Scenario: Share a board
		Given Logging in using web as "user0"
		And creates a board named "Shared board" with color "ff0000"
		And shares the board with user "user1"
			| permissionEdit   | 0 |
			| permissionShare  | 0 |
			| permissionManage | 0 |
		And the response should have a status code 200
		And shares the board with user "user2"
			| permissionEdit   | 1 |
			| permissionShare  | 1 |
			| permissionManage | 1 |
		And the response should have a status code 200

		Given Logging in using web as "user2"
		When fetches the board named "Shared board"
		Then the current user should have "read" permissions on the board
		And the current user should have "edit" permissions on the board
		And the current user should have "share" permissions on the board
		And the current user should have "manage" permissions on the board
		And create a stack named "Stack"
		And the response should have a status code 200
		And create a card named "Test"
		And the response should have a status code 200


		Given Logging in using web as "user1"
		When fetches the board named "Shared board"
		And create a card named "Test"
		And the response should have a status code 403
		Then the current user should have "read" permissions on the board
		And the current user should not have "edit" permissions on the board
		And the current user should not have "share" permissions on the board
		And the current user should not have "manage" permissions on the board
		And create a stack named "Stack"
		And the response should have a status code 403


	Scenario: Reshare a board
		Given Logging in using web as "user0"
		And creates a board named "Shared board" with color "ff0000"
		And shares the board with user "user1"
			| permissionEdit   | 0 |
			| permissionShare  | 1 |
			| permissionManage | 0 |
		And the response should have a status code 200
		Given Logging in using web as "user1"
		When fetches the board named "Shared board"
		And shares the board with user "user2"
			| permissionEdit   | 1 |
			| permissionShare  | 1 |
			| permissionManage | 1 |
		And the response should have a status code 200
		Given Logging in using web as "user2"
		When fetches the board named "Shared board"
		Then the current user should have "read" permissions on the board
		And the current user should not have "edit" permissions on the board
		And the current user should have "share" permissions on the board
		And the current user should not have "manage" permissions on the board

	Scenario: Share a board multiple times
		Given Logging in using web as "user0"
		And creates a board named "Double shared board" with color "ff0000"
		And shares the board with user "user1"
		And shares the board with group "group1"
		And creates a board named "Single shared board" with color "00ff00"
		And shares the board with user "user1"
		When Logging in using web as "user1"
		And fetching the board list
		Then the response should have a status code "200"
		And the response should be a list of objects
		And the response should contain an element with the properties
			| property | value |
			| title | Double shared board |


	Scenario: Deleted board is inaccessible to share recipients
		Given acting as user "user0"
		When creates a board with example content
		And remember the last card as "user0-card"
		When post a comment with content "hello comment" on the card
		And uploads an attachment to the last used card
		And remember the last attachment as "user0-attachment"
		And shares the board with user "user1"
		Then the HTTP status code should be "200"
		And delete the board

		Given acting as user "user1"
		When fetching the attachments for the card "user0-card"
		Then the response should have a status code 403

		When get the comments on the card
		Then the response should have a status code 403

		When update a comment with content "hello deleted" on the card
		Then the response should have a status code 403

		When delete the comment on the card
		Then the response should have a status code 403
		# 644
		When post a comment with content "hello deleted" on the card
		Then the response should have a status code 403

		When get the card details
		Then the response should have a status code 403
		When fetching the attachment "user0-attachment" for the card "user0-card"
		Then the response should have a status code 403
		When deleting the attachment "user0-attachment" for the card "user0-card"
		Then the response should have a status code 403
