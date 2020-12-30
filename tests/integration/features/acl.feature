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
	And creates a board named "MyPrivateAdminBoard" with color "fafafa"
	Given Logging in using web as "user0"
	When fetches the board named "MyPrivateAdminBoard"
	Then the response should have a status code "403"
	And the response Content-Type should be "application/json; charset=utf-8"

	Scenario: Share a board
		Given Logging in using web as "user0"
		And creates a board named "Shared board" with color "fafafa"
		And shares the board with user "user1"
		Then the HTTP status code should be "200"
		Given Logging in using web as "user1"
		When fetches the board named "Shared board"
		And the current user should have read permissions on the board
		And the current user should have write permissions on the board
		And the current user should have share permissions on the board
		And the current user should have manage permissions on the board
		Then the HTTP status code should be "200"
