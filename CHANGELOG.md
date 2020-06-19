# Changelog
All notable changes to this project will be documented in this file.

## 1.0.3 - 2020-06-19

### Fixed

* [#2019](https://github.com/nextcloud/deck/pull/2019) Remove old global css rule @juliushaertl
* [#2020](https://github.com/nextcloud/deck/pull/2020) Fix navigation issue with leftover nodes @juliushaertl
* [#2021](https://github.com/nextcloud/deck/pull/2021) Fix description issues @juliushaertl
* [#2022](https://github.com/nextcloud/deck/pull/2022) Fix replyto issues with the comments API @juliushaertl
* [#2027](https://github.com/nextcloud/deck/pull/2027) Allow to unassign current user from card @juliushaertl
* [#2029](https://github.com/nextcloud/deck/pull/2029) Fix wording : stack -> list @cloud2018
* [#2032](https://github.com/nextcloud/deck/pull/2032) Force order by id as second sorting key @juliushaertl
* [#2045](https://github.com/nextcloud/deck/pull/2045) Improve label styling @juliushaertl
* [#2010](https://github.com/nextcloud/deck/pull/2010) User documentation fixes @Nyco
* [#1998](https://github.com/nextcloud/deck/pull/1998) Add Checklist explaination to the doc @4rnoP


## 1.0.2 - 2020-06-03

### Fixed

* [#1774](https://github.com/nextcloud/deck/pull/1774) Remove deprecated global API calls
* [#1918](https://github.com/nextcloud/deck/pull/1918) Save compact mode on localstorage @jakobroehrl
* [#1919](https://github.com/nextcloud/deck/pull/1919) Show sidebar after card creation @jakobroehrl
* [#1924](https://github.com/nextcloud/deck/pull/1924) Boards ordered in main page @jakobroehrl
* [#1925](https://github.com/nextcloud/deck/pull/1925) Fix generated fronted urls
* [#1944](https://github.com/nextcloud/deck/pull/1944) Move navigation to @nextcloud/vue components
* [#1945](https://github.com/nextcloud/deck/pull/1945) Fix datetime picker
* [#1946](https://github.com/nextcloud/deck/pull/1946) Fix translations
* [#1976](https://github.com/nextcloud/deck/pull/1976) Delete boards that users own once they are deleted
* [#1977](https://github.com/nextcloud/deck/pull/1977) Redirect from previously used routes to the current ones

## 1.0.1 - 2020-05-15

### Fixed

* Removes debug filter output
* Labels are now sorted
* Stack title doesn't break up
* Fix move card modal
* Sort boards in navigation
* Fixes the attachment modal
* Handle deleted boards better
* User can only clone a board on canManage permissions
* Fix modal imports
* Show menu in compact mode
* Added a filter reset button
* Add hover effect to board list
* New filter icon
* Improve hovering response in board
* Enable linkify in description renderer @icewind1991
* Enhance board selector
* Fix issue if card description might be null
* Revert markdown styles from old frontend
* Do not scroll cards into view
* Fix reodering performance

## 1.0.0 - 2020-05-06

### Added

- Completly rewritten frontend
	- Better maintainability
	- Various small fixes
	- Unified user interface with Nextcloud
- Separate comment and activity timelines
- Add ability to reply to comments #1537
- Filter cards on board #1507 @jakobroehrl
- Add cards to projects #1294 @jakobroehrl
- Move cards to other boards #1242 @jakobroehrl
- Clone boards with existing stacks and labels #1221 @jakobroehrl
- Upload multiple files at once and in parallel

A huge thangs goes to our awesome community that put enourmous effort into the frontend migration:

Special thanks for contributing huge parts of the Vue.js migration:
@jakobroehrl @weeman1337 @nicolad

Testers/reporters:
@cloud2018 @putt1ck @bpcurse

Android app team for helping to improve our REST API:
@desperateCoder @stefan-niedermann

## 0.8.0 - 2020-01-16

### Added
- Case insensitive search (@matchish)

### Fixed
- Fix reversed permissions for reordering stacks (@JLueke)
- Fix reversed visibility of 'add stack' field (@JLueke)
- Fix occ export command
- Fix error causing cron execution to fail
- Fix activity entry on moving cards
- Proper wording in activity timeline (@a11exandru)

## 0.7.0 - 2019-08-20

### Added
- Make deck compatible to Nextcloud 17
- Allow to set the description when creating cards though the REST API

## 0.6.6 - 2019-08-01

### Fixed
- Bump security related dependencies

## 0.6.5 - 2019-07-28

### Fixed
- Fix attachment upload/delete failures
- Bump dependencies

## 0.6.4 - 2019-06-30

### Fixed
- Restore stable15 compatibility

## 0.6.3 - 2019-06-30

### Fixed
- Fix issues with comments and activity stream
- Fix setting archived state through API
- Fix type of acl in API responses
- Fix type mismatch with fulltext search

## 0.6.2 - 2019-05-15

### Fixed
- Fix group limit for nonexisting groups
- Only map circle ACLs if the app is enabled
- Fix updating sharing permissions
- Add app version to capabilities

## 0.6.1 - 2019-04-27

### Fixed
- Fix issue with boards not being shown after update
- Fix board selection in projects view outside of deck
- Remove collections text from sidebar
- Remove leftover use statement

## 0.6.0 - 2019-04-23

### Added
- Share boards with circles
- Integration with collections in Nextcloud 16
- Support for full text search
- Nextcloud 16 compatibility

### Fixed
- Fix duplicate call to delete
- Prevent duplicate tag names @jakobroehrl
- Prevent loading details when editing the card title @jakobroehrl
- Hide sidebar after card deletion @jakobroehrl
- Update labels after change in the UI @jakobroehrl
- Allow limiting the app to groups again
- Various REST API enhancements and fixes
- Fix some issues with comments/activites


## 0.5.2 - 2018-12-20

### Fixed
- Mark notification as read if a card with duedate gets archived
- Use proper timezone and locale format for due date activities
- Various translation fixes and updates
- Check group limit properly
- Fix comment activities on Nextcloud 15
- Fix issues with Edge
- API: Fix numeric types that were returned as strings
- API: Fix If-Modified-Since header parsing  


## 0.5.1 - 2018-12-05

### Added
- Separate settings for description changes in activity
- Less verbose description change activities
- Use server settings to restrict sharing to groups
- Add setting to exclude groups from creating their own boards

### Fixed
- Fix issue when using a separate table prefix @bpcurse
- Fix invalid activity parameters being published
- Wording fixes @cloud2018
- Improve loading performance by removing unused activity preloading
- Fix timestamp issues in deleted items tab
- Remember show state of the board navigation @weeman1337
- Add optional classes for custom styling @tinko92
- Fix missing details on activity emails
- Fix unrelated comments in board activity list
- Fix search not working properly
- Trigger comment notification on update only


## 0.5.0 - 2018-11-15

### Added

- Activity stream for board and cards
- Comments on cards
- Use users locale format on date picker
- Compact display mode
- Card title inline editing
- REST API
- Empty content view for board lists
- Undo for card and stack deletion
- Show tag name on board
- Notify users about card assignments
- Add shortcut to assign a card to yourself
- Improved view for printing
- Support for Nextcloud 15

### Fixed

- Accesibility improvements
- Don't allow empty card titles
- Improved checkbox handling in markdown


## 0.4.0 - 2018-07-11

### Added

- Attach files to cards
- Embed attachments into the card description
- Color picker to use any color value for board and labels
- Support for checkboxes inside the description
- occ command to export user data as JSON

### Fixed

- Improve frontend data management
- Fix bug the user list being empty on some occasions

## 0.3.0 - 2018-01-12

### Added
- Allow to assign users to cards
- Emit notifications for overdue cards
- Emit notifications if boards gets shared to a user
- Add support for Nextcloud 13
- Simplify layout for cleaner user experience
- Add contacts menu to avatars
- Automatically save card description on inactivity


### Fixed
- Fix card dragging behaviour
- Fix scrolling and dragging on mobile
- Various fixes when data is not syncronized between different views
- Improved performance
- Update document title when renaming a board
- Automatically chose the least used color
- Improve accessibility
- Fix issue when assigning labels after creating them
- Allow to save tag changes with enter
- Fix bug when removing labels changed the color of the remaining ones
- Fix issues with auto saving of card descriptions


## 0.2.8 - 2017-11-26

### Fixed
- Drop support for NC 13, since that will only be supported by the next version of Deck

## 0.2.7 - 2017-11-10

### Fixed
- Fix bug that caused update to fail

## 0.2.6 - 2017-11-10

### Fixed
- Fix duedates not being updated with MySQL databases

## 0.2.5 - 2017-11-08

### Fixed
- Fix duedates not being saved with MySQL databases

## 0.2.4 - 2017-10-08

### Fixed
- Fix card action menu not being accessible

## 0.2.3 - 2017-09-23

### Fixed
- Fix delete stack button being not available
- Fix acl issues with PostgreSQL

## 0.2.2 - 2017-09-07

### Fixed
- Various frontend fixes
- Fix sidebar drag issues
- Improvements for IE11 
- Fix bug when draging a card to an empty stack

## 0.2.1 - 2017-07-04

### Added
- Editing board details in board list
- Due date on mouse over

### Changed
- Polished label editor
- Polished sidebar
- UI improvements in board view
- Moved to SCSS

### Fixed
- Fix opacity of last entry in board list

## 0.2.0 - 2017-06-20

### Added
- Due dates for cards
- Archive boards
- Filter board list for archived/shared boards
- Rearange stack order
- Improved card overview with description indicator
- Navigation sidebar visibility can be toggled

### Fixed
- Undo on delete for boards
- Various fixes for mobile devices
- UI improvements to fit the Nextcloud design

## 0.1.4 - 2017-05-04

### Fixed
- Avoid red shadow on input in firefox
- Fix broken delete function for boards
- Fix broken board loading when groups were used for sharing
- Fix bug when users/groups got deleted

## 0.1.3 - 2017-05-01

### Added
- Icon to show if a card has a description

### Changed
- Use OCS API to get users/groups for sharing
- Various UI improvements
- Show display name instead of uid
- Fix bugs with limited field length
- Automatically hide sidebar when clicking the board view
- Start editing from everywhere in the description section


## 0.1.2

### Added
- Add translations

### Fixed
- Fix issues with Acl checks
- Always select first color fixes
- Add active class to appmenu
- Use server select2 styles
- Remove debug logging and unused function
- Fix issue while sorting cards
- Improve logging of exceptions
- Fixed SQL statements without prefixes

## 0.1.1

### Fixed
- Various styling improvements
- Fix problems with MySQL and PostgreSQL 
- Select first color by default when creating boards
- Fix error when changing board permissions

## 0.1.0

### Added
- Sharing boards with other users
- Create and manage boards 
- Sort cards on stacks by drag-and-drop
- Assign labels
- Markdown notes for each card
- Archive cards 

