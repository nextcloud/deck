<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# REST API

The REST API provides access for authenticated users to their data inside the Deck app. To get a better understanding of Decks data models and their relations, please have a look at the  [data structure](structure.md) documentation.

## Prerequisites

- All requests require a `OCS-APIRequest` HTTP header to be set to `true` and a `Content-Type` of `application/json`.
- The API is located at `/index.php/apps/deck/api/v1.0`
- All request parameters are required, unless otherwise specified

### Glossary

- **Board** is the project like grouping of tasks that can be shared to different users and groups
- **Stack** is the grouping of cards which is rendered in vertical columns in the UI
- **Card** is the representation of a single task
- **Label** is a board-level tag used to categorize and prioritize cards

### HTTP responses

The REST API follows conventional HTTP status codes. Successful responses have a status code of `2xx`. Client side errors are indicated as `4xx`. The most common ones are:

#### 400 Bad request

The request is invalid, e.g. because a parameter is missing or an invalid value has been transmitted.

```json
{
  "status": 400,
  "message": "title must be provided"
}
```

#### 403 Permission denied

The user doesn't have access to a requested entity.

```json
{
    "status": 403,
    "message": "Permission denied"
}
```

#### 404 Not found

The requested entity was not found.

```json
{
    "status": 404,
    "message": "Card not found"
}
```

#### 405 Method not allowed

The used combination of URL and HTTP method is not allowed. Most likely you have used a wrong HTTP method or URL.

```json
{
    "status": 405,
    "message": "Method not allowed"
}
```

### Formats

#### Date

Datetime values in request data need to be provided in ISO-8601.  
Example: `2020-01-20T09:52:43+00:00`

### Headers

#### If-Modified-Since

Some index endpoints support limiting the result set to entries that have been changed since the given time.
The supported date formats are:

- IMF-fixdate:                 `Sun, 03 Aug 2019 10:34:12 GMT`
- _(obsolete)_ RFC 850:          `Sunday, 03-Aug-19 10:34:12 GMT`
- _(obsolete)_ ANSI C asctime(): `Sun Aug  3 10:34:12 2019`

It is highly recommended to only use the IMF-fixdate format. Note that according to [RFC2616](https://tools.ietf.org/html/rfc2616#section-3.3) all HTTP date/time stamps MUST be represented in Greenwich Mean Time (GMT), without exception.

Example curl request:

```bash
curl -u admin:admin -X GET \
    'http://localhost:8000/index.php/apps/deck/api/v1.0/boards/2/stacks' \
    -H "OCS-APIRequest: true" \
    -H "If-Modified-Since: Mon, 05 Nov 2018 09:28:00 GMT"
```

#### ETag

An ETag header is returned in order to determine if further child elements have been updated for the following endpoints:

- Fetch all user board `GET /api/v1.0/boards`
- Fetch a single board `GET /api/v1.0/boards/{boardId}`
- Fetch all stacks of a board `GET /api/v1.0/boards/{boardId}/stacks`
- Fetch a single stacks of a board `GET /api/v1.0/boards/{boardId}/stacks/{stackId}`
- Fetch a single card of a board `GET /api/v1.0/boards/{boardId}/stacks/{stackId}/cards/{cardId}`
- Fetch attachments of a card `GET /api/v1.0/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments`

If a `If-None-Match` header is provided and the requested element has not changed a `304` Not Modified response will be returned.

Changes of child elements will propagate to their parents and also cause an update of the ETag which will be useful for determining if a sync is necessary on any client integration side. As an example, if a label is added to a card, the ETag of all related entities (the card, stack and board) will change.

If available the ETag will also be part of JSON response objects as shown below for a card:

```json
{
  "id": 81,
  "ETag": "bdb10fa2d2aeda092a2b6b469454dc90",
  "title": "Test card"
}
```

#### x-nc-deck-session

The `x-nc-deck-session` header can be used when the [notify push client](https://github.com/nextcloud/notify_push) is active to receive live updates via websockets in clients. This is useful when multiple users are working on the same board at the same time. 

The header allows the server to detect who caused a certain update (e.g. create a card) and not notify the causing user about the change. This makes client logic easier, because the client has to do less work to understand which incoming live change was already applied locally.

The value is the session token returned from the [create session endpoint](#create-session).

## Changelog

### API version 1.0

- Deck >=1.0.0: The maximum length of the card title has been extended from 100 to 255 characters
- Deck >=1.0.0: The API will now return a 400 Bad request response if the length limitation of a board, stack or card title is exceeded

### API version 1.1

This API version has become available with **Deck 1.3.0**.

- The maximum length of the card title has been extended from 100 to 255 characters
- The API will now return a 400 Bad request response if the length limitation of a board, stack or card title is exceeded
- The attachments API endpoints will return other attachment types than deck_file
  - Prior to Deck version v1.3.0 (API v1.0), attachments were stored within deck. For this type of attachments `deck_file` was used as the default type of attachments
  - Starting with Deck version 1.3.0 (API v1.1) files are stored within the user's regular Nextcloud files and the type `file` has been introduced for that

### API version 1.2 (unreleased)

- Endpoints for the new import functionality have been added:
    - [GET /boards/import/getSystems - Get Systems](#get-systems)
    - [GET /boards/import/config/system/{schema} - Get System Schema](#get-system-schema)
    - [POST /boards/import - Import board](#import-board)
- The `done` property was added to cards

## Endpoints

### Boards

#### List boards {.ep-heading}

Get a list of all user boards.

##### Path

`GET /boards`{.ep-path}

##### Headers

The board list endpoint supports setting an `If-Modified-Since` header to limit the results to entities that are changed after the provided time.

##### Query string parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| details   | Bool    | _(optional)_ enhance boards with details about labels, stacks and users |

##### Response

```json
[
    {
        "title": "Board title",
        "owner": {
            "primaryKey": "admin",
            "uid": "admin",
            "displayname": "Administrator"
        },
        "color": "ff0000",
        "archived": false,
        "labels": [],
        "acl": [],
        "permissions": {
            "PERMISSION_READ": true,
            "PERMISSION_EDIT": true,
            "PERMISSION_MANAGE": true,
            "PERMISSION_SHARE": true
        },
        "users": [],
        "shared": 0,
        "deletedAt": 0,
        "id": 10,
        "lastModified": 1586269585,
        "settings": {
            "notify-due": "off",
            "calendar": true
        }
    }
]
```

#### Create board {.ep-heading}

Create a new board. The user's ability to create new boards can be disabled by the administrator. For checking this before, see the `canCreateBoards` value in the [Nextcloud capabilties](./API-Nextcloud.md).

##### Path

`POST /boards`{.ep-path}

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the new board, maximum length is limited to 100 characters |
| color     | String | The hexadecimal color of the new board (e.g. FF0000) |

###### Example

```json
{
    "title": "Board title",
    "color": "ff0000"
}
```

##### Response

```json
{
    "title": "Board title",
    "owner": {
        "primaryKey": "admin",
        "uid": "admin",
        "displayname": "Administrator"
    },
    "color": "ff0000",
    "archived": false,
    "labels": [
        {
            "title": "Finished",
            "color": "31CC7C",
            "boardId": 10,
            "cardId": null,
            "id": 37
        },
        {
            "title": "To review",
            "color": "317CCC",
            "boardId": 10,
            "cardId": null,
            "id": 38
        },
        {
            "title": "Action needed",
            "color": "FF7A66",
            "boardId": 10,
            "cardId": null,
            "id": 39
        },
        {
            "title": "Later",
            "color": "F1DB50",
            "boardId": 10,
            "cardId": null,
            "id": 40
        }
    ],
    "acl": [],
    "permissions": {
        "PERMISSION_READ": true,
        "PERMISSION_EDIT": true,
        "PERMISSION_MANAGE": true,
        "PERMISSION_SHARE": true
    },
    "users": [],
    "deletedAt": 0,
    "id": 10,
    "lastModified": 1586269585
}
```

#### Get board {.ep-heading}

Get a board by ID.

##### Path

`GET /boards/{boardId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

```json
{
    "title": "Board title",
    "owner": {
        "primaryKey": "admin",
        "uid": "admin",
        "displayname": "Administrator"
    },
    "color": "ff0000",
    "archived": false,
    "labels": [
        {
            "title": "Finished",
            "color": "31CC7C",
            "boardId": "10",
            "cardId": null,
            "id": 37
        },
        {
            "title": "To review",
            "color": "317CCC",
            "boardId": "10",
            "cardId": null,
            "id": 38
        },
        {
            "title": "Action needed",
            "color": "FF7A66",
            "boardId": "10",
            "cardId": null,
            "id": 39
        },
        {
            "title": "Later",
            "color": "F1DB50",
            "boardId": "10",
            "cardId": null,
            "id": 40
        }
    ],
    "acl": [],
    "permissions": {
        "PERMISSION_READ": true,
        "PERMISSION_EDIT": true,
        "PERMISSION_MANAGE": true,
        "PERMISSION_SHARE": true
    },
    "users": [
        {
            "primaryKey": "admin",
            "uid": "admin",
            "displayname": "Administrator"
        }
    ],
    "deletedAt": 0,
    "id": 10
}
```

#### Update board {.ep-heading}

Update a board by ID.

##### Path

`PUT /boards/{boardId}`{.ep-path}

##### Path parameters

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| boardId   | Integer | The id of the board |

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the board, maximum length is limited to 100 characters |
| color     | String | The hexadecimal color of the board (e.g. FF0000) |
| archived  | Bool   | Whether or not this board should be archived. |

###### Example

```json
{
    "title": "Board title",
    "color": "ff0000",
    "archived": false
}
```

##### Response

Returns the updated board.

#### Delete board {.ep-heading}

Delete a board by ID.

##### Path

`DELETE /boards/{boardId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to delete |

##### Response

Returns the deleted board. The `deletedAt`-key contains the UNIX timestamp at deletion.

#### Restore board {.ep-heading}

Restore a deleted board by ID.

##### Path

`POST /boards/{boardId}/undo_delete`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to restore |

##### Response

Returns the restored board.

#### Clone board {.ep-heading}

Clone a board by ID.

##### Path

`POST /boards/{boardId}/clone`{.ep-path}

Create a copy of the board.

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board |

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| withCards  | Bool   | Setting if the cards should be copied (Default: false) |
| withAssignments  | Bool   | Setting if the card assignments should be cloned (Default: false) |
| withLabels  | Bool   | Setting if the card labels should be cloned (Default: false) |
| withDueDate  | Bool   | Setting if the card due dates should be cloned (Default: false) |
| moveCardsToLeftStack  | Bool   | Setting if all cards should be moved to the most left column (useful for To-Do / Doing / Done boards) (Default: false) |
| restoreArchivedCards  | Bool   | Setting if the archived cards should be unarchived (Default: false) |

##### Response

Returns the restored board.

#### Create ACL rule {.ep-heading}

Create an ACL for a board.

##### Path

`POST /boards/{boardId}/acl`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board |

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| type      | Integer | Type of the participant                              |
| participant     | String | The uid of the participant |
| permissionEdit  | Bool   | Setting if the participant has edit permissions |
| permissionShare  | Bool   | Setting if the participant has sharing permissions |
| permissionManage  | Bool   | Setting if the participant has management permissions |

###### Supported participant types:
- 0 User
- 1 Group
- 7 Circle

##### Response

```json
[{
  "participant": {
    "primaryKey": "userid",
    "uid": "userid",
    "displayname": "User Name"
  },
  "type": 0,
  "boardId": 1,
  "permissionEdit": true,
  "permissionShare": false,
  "permissionManage": true,
  "owner": false,
  "id": 1
}]
```

#### Update ACL rule {.ep-heading}

Update an ACL by ID.

##### Path

`PUT /boards/{boardId}/acl/{aclId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board |
| aclId   | Integer | The id of the acl |

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| permissionEdit  | Bool   | Setting if the participant has edit permissions |
| permissionShare  | Bool   | Setting if the participant has sharing permissions |
| permissionManage  | Bool   | Setting if the participant has management permissions |

##### Response

Returns the updated ACL.

#### Delete ACL rule {.ep-heading}

Delete an ACL by ID.

##### Path

`DELETE /boards/{boardId}/acl/{aclId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board |
| aclId   | Integer | The id of the acl |

##### Response

Returns the deleted ACL.

### Stacks

#### List stacks {.ep-heading}

Get a list of all board stacks.

##### Path

`GET /boards/{boardId}/stacks`{.ep-path}

##### Headers

The board list endpoint supports setting an `If-Modified-Since` header to limit the results to entities that are changed after the provided time.

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

```json
[
  {
    "title": "ToDo",
    "boardId": 2,
    "deletedAt": 0,
    "lastModified": 1541426139,
    "cards": [...],
    "order": 999,
    "id": 4
  }
]
```

#### List archived stacks {.ep-heading}

Get a list of archived stacks

##### Path

`GET /boards/{boardId}/stacks/archived`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

```json
[
  {
    "title": "ToDo",
    "boardId": 2,
    "deletedAt": 0,
    "lastModified": 1541426139,
    "cards": [...],
    "order": 999,
    "id": 4
  }
]
```

#### Get stack {.ep-heading}

Get a stack by ID.

##### Path

`GET /boards/{boardId}/stacks/{stackId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

##### Response

```json
{
  "title": "ToDo",
  "boardId": 2,
  "deletedAt": 0,
  "lastModified": 1541426139,
  "cards": [...],
  "order": 999,
  "id": 4
}
```

#### Create stack {.ep-heading}

Create a stack on the board.

##### Path

`POST /boards/{boardId}/stacks`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the new stack, maximum length is limited to 100 characters |
| order     | Integer | Order for sorting the stacks                         |

##### Response

Returns the created stack.

#### Update stack {.ep-heading}

Update a stack by ID.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the stack, maximum length is limited to 100 characters |
| order     | Integer | Order for sorting the stacks                         |

##### Response

Returns the updated stack.

#### Delete stack {.ep-heading}

Delete a stack by ID.

##### Path

`DELETE /boards/{boardId}/stacks/{stackId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

##### Response

Returns the deleted stack. The `deletedAt`-key contains the UNIX timestamp at deletion.

### Cards

#### Get card {.ep-heading}

Get a card by ID.

##### Path

`GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

```json
{
   "title":"Test",
   "description":null,
   "stackId":6,
   "type":"plain",
   "lastModified":1541528026,
   "createdAt":1541528026,
   "labels":null,
   "assignedUsers":null,
   "attachments":null,
   "attachmentCount":null,
   "owner":"admin",
   "order":999,
   "archived":false,
   "done":null,
   "duedate": "2019-12-24T19:29:30+00:00",
   "deletedAt":0,
   "commentsUnread":0,
   "id":10,
   "overdue":0
}
```

#### Create card {.ep-heading}

Crreate a card on the board stack.

##### Path

`POST /boards/{boardId}/stacks/{stackId}/cards`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the card, maximum length is limited to 255 characters |
| type      | String  | Type of the card (for later use) use 'plain' for now |
| order     | Integer | Order for sorting the stacks                         |
| description | String  | _(optional)_ The markdown description of the card  |
| duedate   | timestamp | _(optional)_ The duedate of the card or null       |

##### Response

```json
{
   "title":"Test",
   "description":null,
   "stackId":6,
   "type":"plain",
   "lastModified":1541528026,
   "createdAt":1541528026,
   "labels":null,
   "assignedUsers":null,
   "attachments":null,
   "attachmentCount":null,
   "owner":"admin",
   "order":999,
   "archived":false,
   "done":null,
   "duedate": "2019-12-24T19:29:30+00:00",
   "deletedAt":0,
   "commentsUnread":0,
   "id":10,
   "overdue":0
}
```

#### Update card {.ep-heading}

Update a card by ID.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter   | Type            | Description                                                                                         |
|-------------|-----------------|-----------------------------------------------------------------------------------------------------|
| title       | String          | The title of the card, maximum length is limited to 255 characters                                  |
| description | String          | The markdown description of the card                                                                |
| type        | String          | Type of the card (for later use) use 'plain' for now                                                |
| owner       | String          | The user that owns the card                                                                         |
| order       | Integer         | Order for sorting the stacks                                                                        |
| duedate     | timestamp \| null       | The ISO-8601 formatted duedate of the card or null                                                  |
| archived    | bool            | Whether the card is archived or not                                                                 |
| done        | timestamp \| null | _(optional)_ The ISO-8601 formatted date when the card is marked as done (null indicates undone state) |

###### Example

```json
{
   "title": "Test card",
   "description": "A card description",
   "type": "plain",
   "owner": "admin",
   "order": 999,
   "duedate": "2019-12-24T19:29:30+00:00",
   "archived": false,
   "done": null,
}
```

##### Response

Returns the updated card.

#### Delete card {.ep-heading}

Delete a card by ID.

##### Path

`DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

Returns the deleted card. The `deletedAt`-key contains the UNIX timestamp at deletion.

#### Assign label {.ep-heading}

Assign a board label to a card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignLabel`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| labelId   | Integer | The label id to assign to the card      |

##### Response

Returns an empty response.

#### Unassign label {.ep-heading}

Unassign a board label from a card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/removeLabel`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| labelId   | Integer | The label id to remove to the card      |

##### Response

Returns an empty response.

#### Assign user {.ep-heading}

Assign a board user to a card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignUser`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| userId    | String  | The user id to assign to the card       |

##### Response

```json
{
  "id": 3,
  "participant": {
    "primaryKey": "admin",
    "uid": "admin",
    "displayname": "admin"
  },
  "cardId": 1
}
```

#### Unassign user {.ep-heading}

Unassing a user from a card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/unassignUser`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| userId    | String  | The user id to unassign from the card   |

##### Response

Returns the removed user assignment.

#### Move card {.ep-heading}

Update the order and/or the stack of the card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/reorder`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter | Type    | Description                                                 |
| --------- | ------- | ----------------------------------------------------------- |
| order     | Integer | The position in the stack where the card should be moved to |
| stackId   | Integer | The id of the stack where the card should be moved to       |

##### Response

Returns a list of stack cards in the updated order.

#### Archive card {.ep-heading}

Archive a card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/archive`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

Returns the archived card.

#### Unarchive card {.ep-heading}

Unarchive a card.

##### Path

`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/unarchive`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

Returns the unarchived card.

### Labels

#### Get label {.ep-heading}

Get a label by ID.

##### Path

`GET /boards/{boardId}/labels/{labelId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

##### Response

```json
{
  "title": "Abgeschlossen",
  "color": "31CC7C",
  "boardId": "2",
  "cardId": null,
  "id": 5
}
```

#### Create label {.ep-heading}

Create a label.

##### Path

`POST /boards/{boardId}/labels`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |

##### Request body

```json
{
  "title": "Finished",
  "color": "31CC7C"
}
```

##### Response

```json
{
  "title": "Finished",
  "color": "31CC7C",
  "boardId": "2",
  "cardId": null,
  "id": 5
}
```

#### Update label {.ep-heading}

Update a label by ID.

##### Path

`PUT /boards/{boardId}/labels/{labelId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

##### Request body

```json
{
  "title": "Finished",
  "color": "31CC7C"
}
```

##### Response

Returns the updated label.

#### Delete label {.ep-heading}

Delete a label by ID.

##### Path

`DELETE /boards/{boardId}/labels/{labelId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

##### Response

Returns the deleted label.

### Attachments

#### List attachments {.ep-heading}

Get a list of all card attachments. When api version `v1.0` is used, then this endpoint returns only
attachments of type `deck_file`.

##### Path

`GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

```json
[
  {
    "cardId": 5,
    "type": "deck_file",
    "data": "6DADC2C69F4.eml",
    "lastModified": 1541529048,
    "createdAt": 1541529048,
    "createdBy": "admin",
    "deletedAt": 0,
    "extendedData": {
      "filesize": 922258,
      "mimetype": "application/octet-stream",
      "info": {
        "dirname": ".",
        "basename": "6DADC2C69F4.eml",
        "extension": "eml",
        "filename": "6DADC2C69F4"
      }
    },
    "id": 6
  }
]

```

#### Get attachment {.ep-heading}

Get a card attachment by ID.

##### Path

v1.0  
`GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}`{.ep-path}

v1.1  
`GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}`{.ep-path}

##### Path parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |
| type | String | `file` \| `deck_file` |

##### Response

Returns the card attachment.

#### Upload attachment {.ep-heading}

Upload a card attachment.

##### Path

`POST /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| boardId   | Integer | The id of the board the attachment belongs to |
| stackId   | Integer | The id of the stack the attachment belongs to |
| cardId    | Integer | The id of the card the attachment belongs to  |

##### Request body

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| type      | String  | The type of the attachement                   |
| file      | Binary  | File data to add as an attachment             |

- Prior to Deck version v1.3.0 (API v1.0), attachments were stored within deck. For this type of attachments `deck_file` was used as the default type of attachments
- Starting with Deck version 1.3.0 (API v1.1) files are stored within the user's regular Nextcloud files and the type `file` has been introduced for that

##### Response

Returns the card attachement.

#### Update attachment {.ep-heading}

Update a card attachment by ID.

##### Path
v1.0  
`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}`{.ep-path}

v1.1  
`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}`{.ep-path}

##### Path parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |
| type | String | `file` \| `deck_file` |

##### Request body

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| type      | String  | The type of the attachement                   |
| file      | Binary  | File data to add as an attachment             |

For now only `deck_file` is supported as an attachment type.

##### Response

Returns the updated card attachment.

#### Delete attachment {.ep-heading}

Delete a card attachment by ID.

##### Path

v1.0  
`DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}`{.ep-path}

v1.1  
`DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}`{.ep-path}

##### Path parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |
| type | String | `file` \| `deck_file` |

##### Response

Returns the deleted attachment.

#### Restore attachment {.ep-heading}

Restore a deleted card attachment by ID.

##### Path
v1.0  
`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}/restore`{.ep-path}

v1.1  
`PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}/restore`{.ep-path}

##### Path parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |
| type | String | `file` \| `deck_file` |

##### Response

Returns the restored attachment.

### Import API

#### Get Systems {.ep-heading}

Get the allowed import systems.

##### Path

`GET /boards/import/getSystems`{.ep-path}

##### Response

```json
[
  "trello"
]
```

#### Get System Schema {.ep-heading}

Get a system schema.

##### Path

`GET /boards/import/config/system/{system}`{.ep-path}

##### Path parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| system       | Integer | The system name. Example: trello              |

##### Response

```json
{}
```

#### Import board {.ep-heading}

Import a board from another system.

##### Path

`POST /boards/import`{.ep-path}

##### Path parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| system       | string  | The allowed name of system to import from     |
| config       | Object  | The config object  (JSON)                     |
| data         | Object  | The data object to import (JSON)              |

##### Response

Returns the imported board.

## OCS API

The following endpoints are available through the Nextcloud OCS endpoint, which is available at `/ocs/v2.php/apps/deck/api/v1.0/`.
This has the benefit that both the web UI as well as external integrations can use the same API.

### Config

Deck stores user and app configuration values globally and per board. The GET endpoint allows to fetch the current global configuration while board settings will be exposed through the board element on the regular API endpoints.

#### Get app configuration {.ep-heading}

Get the configuration of the deck app.

##### Path

`GET /api/v1.0/config`{.ep-path}

##### Response

| Config key | Description |
| --- | --- |
| calendar | Determines if the calendar/tasks integration through the CalDAV backend is enabled for the user (boolean) |
| cardDetailsInModal | Determines if the bigger view is used (boolean) |
| cardIdBadge | Determines if the ID badges are displayed on cards (boolean) |
| groupLimit | Determines if creating new boards is limited to certain groups of the instance. The resulting output is an array of group objects with the id and the displayname (Admin only)|

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "calendar": true,
      "cardDetailsInModal": true,
      "cardIdBadge": true,
      "groupLimit": [
        {
          "id": "admin",
          "displayname": "admin"
        }
      ]
    }
  }
}

```

#### Set config value {.ep-heading}

Set a configuration value by key.

##### Path

`POST /api/v1.0/config/{key}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| key     | String | The config key to set, prefixed with `board:{boardId}:` to apply setting only for a specific board |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| value    | Any | The value that should be stored for the config key |

###### Example

```bash
curl -X POST 'https://admin:admin@nextcloud.local/ocs/v2.php/apps/deck/api/v1.0/config/calendar'
 -H 'Accept: application/json'
 -H "Content-Type: application/json"
 -H 'OCS-APIRequest: true'
 --data-raw '{"value":false}'
```

###### Board configuration options

| Key | Value |
| --- | ----- |
| notify-due | `off`, `assigned` or `all` |
| calendar | Boolean |
| cardDetailsInModal | Boolean |
| cardIdBadge | Boolean |

##### Response

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": false
  }
}
```

### Comments

#### List comments {.ep-heading}

List comments for a card.

##### Path

`GET /cards/{cardId}/comments`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| limit     | Integer | The maximum number of comments that should be returned, defaults to 20 |
| offset    | Integer | The start offset used for pagination, defaults to 0 |

###### Example

```bash
curl 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
```

##### Response

A list of comments will be provided under the `ocs.data` key. If no or no more comments are available the list will be empty.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": [
      {
        "id": 175,
        "objectId": 12,
        "message": "This is a comment with a mention to  @alice",
        "actorId": "admin",
        "actorType": "users",
        "actorDisplayName": "Administrator",
        "creationDateTime": "2020-03-10T10:23:07+00:00",
        "mentions": [
          {
            "mentionId": "alice",
            "mentionType": "user",
            "mentionDisplayName": "alice"
          }
        ]
      }
    ]
  }
}
```

In case a comment is marked as a reply to another comment object, the parent comment will be added as `replyTo` entry to the response. Only the next parent node is added, nested replies are not exposed directly.

```json
[
  {
    "id": 175,
    "objectId": 12,
    "message": "This is a comment with a mention to  @alice",
    "actorId": "admin",
    "actorType": "users",
    "actorDisplayName": "Administrator",
    "creationDateTime": "2020-03-10T10:23:07+00:00",
    "mentions": [
      {
        "mentionId": "alice",
        "mentionType": "user",
        "mentionDisplayName": "alice"
      }
    ],
    "replyTo": {
     "id": 175,
     "objectId": 12,
     "message": "This is a comment with a mention to  @alice",
     "actorId": "admin",
     "actorType": "users",
     "actorDisplayName": "Administrator",
     "creationDateTime": "2020-03-10T10:23:07+00:00",
     "mentions": [
       {
         "mentionId": "alice",
         "mentionType": "user",
         "mentionDisplayName": "alice"
       }
     ]
   }
  }
]
```

#### Create comment {.ep-heading}

Create comment for a card.

##### Path

`POST /cards/{cardId}/comments`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| message     | String | The message of the comment, maximum length is limited to 1000 characters |
| parentId    | Integer \| null | The id of the parent comment (when replying) |

Mentions will be parsed by the server. The server will return a list of mentions in the response to this request as shown below.

###### Example

```bash
curl -X POST 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
    -H 'Content-Type: application/json;charset=utf-8'
    --data '{"message":"My message to @bob","parentId":null}'
```

##### Response

The created comment will be provided under the `ocs.data` key.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "id": "177",
      "objectId": "13",
      "message": "My message to @bob",
      "actorId": "admin",
      "actorType": "users",
      "actorDisplayName": "Administrator",
      "creationDateTime": "2020-03-10T10:30:17+00:00",
      "mentions": [
        {
          "mentionId": "bob",
          "mentionType": "user",
          "mentionDisplayName": "bob"
        }
      ]
    }
  }
}
```

#### Update comment {.ep-heading}

Update a card comment by ID. Updating comments is limited to the current user being the same as the comment author specified in the `actorId` of the comment.

##### Path

`PUT /cards/{cardId}/comments/{commentId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| commentId    | Integer | The id of the comment                      |

##### Request body

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| message     | String | The message of the comment, maximum length is limited to 1000 characters |

Mentions will be parsed by the server. The server will return a list of mentions in the response to this request as shown below.

###### Example

```bash
curl -X PUT 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments/123' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
    -H 'Content-Type: application/json;charset=utf-8'
    --data '{"message":"My message"}'
```

##### Response

The updated comment will be provided under the `ocs.data` key.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "id": "177",
      "objectId": "13",
      "message": "My message",
      "actorId": "admin",
      "actorType": "users",
      "actorDisplayName": "Administrator",
      "creationDateTime": "2020-03-10T10:30:17+00:00",
      "mentions": []
    }
  }
}
```

#### Delete comment {.ep-heading}

Delete a card comment by ID. Deleting comments is limited to the current user being the same as the comment author specified in the `actorId` of the comment.

##### Path

`DELETE /cards/{cardId}/comments/{commentId}`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| commentId    | Integer | The id of the comment                      |

##### Example

```bash
curl -X DELETE 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments/123' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
    -H 'Content-Type: application/json;charset=utf-8'
```

##### Response

A list of comments will be provided under the `ocs.data` key. If no or no more comments are available the list will be empty.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": []
  }
}
```

### Sessions

#### Create session {.ep-heading}

Create a session.

##### Path

`PUT /session/create`{.ep-path}

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| boardId   | Integer | The id of the opened board |

###### Example

```bash
curl -X PUT 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/session/create' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true' \
    -H 'Content-Type: application/json;charset=utf-8' \
    --data '{"boardId":1}'
```

##### Response

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "token": "+zcJHf4rC6dobVSbuNa3delkCSfTW8OvYWTyLFvSpIv80FjtgLIj0ARlxspsazNQ"
    }
  }
}
```

#### Sync session {.ep-heading}

Notify the server that the session is still open.

##### Path

`POST /session/sync`{.ep-path}

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| boardId   | Integer | The id of the opened board |
| token     | String  | The session token from the /sessions/create response |

###### Example

```bash
curl -X POST 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/session/create' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true' \
    -H 'Content-Type: application/json;charset=utf-8' \
    --data '{"boardId":1, "token":"X3DyyoFslArF0t0NBZXzZXzcy8feoX/OEytSNXZtPg9TpUgO5wrkJ38IW3T/FfpV"}'
```

##### Response

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": []
  }
}
```

#### Close session {.ep-heading}

Close a session.

##### Path

`POST /session/close`{.ep-path}

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| boardId   | Integer | The id of the opened board                           |
| token     | String  | The session token from the /sessions/create response |

###### Example

```bash
curl -X POST 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/session/close' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true' \
    -H 'Content-Type: application/json;charset=utf-8' \
    --data '{"boardId":1, "token":"X3DyyoFslArF0t0NBZXzZXzcy8feoX/OEytSNXZtPg9TpUgO5wrkJ38IW3T/FfpV"}'
```

##### Response

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": []
  }
}
```

### Overview API

#### List upcoming cards {.ep-heading}

Get a list of cards across all user boards. The cards are grouped by their due dates:

- overdue
- today
- tomorrow
- next 7 days
- later
- no due

##### Path

`GET /overview/upcoming`{.ep-path}

##### Response

The `ocs.data` key contains card groups that map to card arrays. A group without any cards will not be present in the result. Unlike the cards returned from the other API endpoints, upcoming cards embed a `board` object that contains the board's ID and title.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "overdue": [...],
      "today": [...],
      "tomorrow": [...],
      "nextSevenDays": [...],
      "later": [...],
      "nodue": [...],
    }
  }
}
```

### Cards

#### Clone card {.ep-heading}

Clone a card.

##### Path

`POST /cards/${cardId}/clone`{.ep-path}

##### Path parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card to be cloned                      |

##### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| targetStackId   | Integer | _(optional)_ The id of the target stack, defaults to the current stack                           |

##### Response

The `ocs.data` key contains the cloned card.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "title":"Clone",
      "description":null,
      "stackId":6,
      "type":"plain",
      "lastModified":1541528026,
      "createdAt":1541528026,
      "labels":null,
      "assignedUsers":null,
      "attachments":null,
      "attachmentCount":null,
      "owner":"admin",
      "order":999,
      "archived":false,
      "done":null,
      "duedate": "2019-12-24T19:29:30+00:00",
      "deletedAt":0,
      "commentsUnread":0,
      "id":10,
      "overdue":0
    }
  }
}
```

#### Search cards {.ep-heading}

Search cards across all user boards that match the search term in title or description. Returned cards are sorted in decreasing order by `last_modified`.

##### Path

`GET /search?term=test`{.ep-path}

##### Query string parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| term    | String | search term                      |
| limit    | Integer | _(optional)_ maximum number of returned results                    |
| cursor    | Integer | _(optional)_ UNIX timestamp in second. When set, only cards which were modified before `cursor` will be retrieved |

##### Response

The `ocs.data` key contains the matching cards. Unlike cards from the other API endpoint, these cards contain richer data of their stack and board under `relatedStack` and `relatedBoard` resp.

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": { 
      [
        "title":"Clone",
        "description":null,
        "stackId":6,
        "type":"plain",
        "lastModified":1541528026,
        "createdAt":1541528026,
        "labels":null,
        "assignedUsers":null,
        "attachments":null,
        "attachmentCount":null,
        "owner":"admin",
        "order":999,
        "archived":false,
        "done":null,
        "duedate": "2019-12-24T19:29:30+00:00",
        "deletedAt":0,
        "commentsUnread":0,
        "id":10,
        "overdue":0,
        "relatedBoard":  {
          ...
        },
        "relatedStack": {
          ...
        }
      ]
    }
  }
}
```
