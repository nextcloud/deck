# Changelog
All notable changes to this project will be documented in this file.

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

