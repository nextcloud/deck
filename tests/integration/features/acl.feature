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

  Scenario: Request the main frontend page
	Given Logging in using web as "user0"
	When Sending a "GET" to "/index.php/apps/deck" without requesttoken
	Then the HTTP status code should be "200"

  Scenario: Fetch the board list
	Given Logging in using web as "user0"
	When Sending a "GET" to "/index.php/apps/deck/boards" with requesttoken
	Then the HTTP status code should be "200"
	And the Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of owned board
	Given Logging in using web as "admin"
	And creates a board named "MyPrivateAdminBoard" with color "fafafa"
	When "admin" fetches the board named "MyPrivateAdminBoard"
	Then the HTTP status code should be "200"
	And the Content-Type should be "application/json; charset=utf-8"

  Scenario: Fetch board details of an other users board
	Given Logging in using web as "admin"
	And creates a board named "MyPrivateAdminBoard" with color "fafafa"
	When "user0" fetches the board named "MyPrivateAdminBoard"
	Then the HTTP status code should be "403"
	And the Content-Type should be "application/json; charset=utf-8"