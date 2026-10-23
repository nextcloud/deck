<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
The REST API provides access for authenticated users to their data inside the Deck app. To get a better understanding of Deck's data models and their relations, please have a look at the [data structure](structure.md) documentation.

## API selection

Deck exposes two HTTP APIs:

- Prefer the OCS API at `/ocs/v2.php/apps/deck/api/v1.0/` for all integrations and newly written clients. This is the canonical API used by the Deck web UI and the server-side access points in the app code.
- The legacy app API at `/index.php/apps/deck/api/v1.0/` still exists for backwards compatibility and is kept available, but it is not the recommended integration path for new projects.

Use the OCS routes for all new integrations. The legacy `/index.php/apps/deck/api/v1.0/` endpoints still work for backwards compatibility, but they are not the preferred API contract for clients.

# Prerequisites

- OCS requests require a `OCS-APIRequest` HTTP header to be set to `true` and a `Content-Type` of `application/json` unless otherwise specified.
- The preferred OCS API base URL is `https://nextcloud.local/ocs/v2.php/apps/deck/api/v1.0/`
- The legacy API base URL is `https://nextcloud.local/index.php/apps/deck/api/v1.0/`
- All request parameters are required, unless otherwise specified

## Naming

- Board is the project like grouping of tasks that can be shared to different users and groups

- Stack is the grouping of cards which is rendered in vertical columns in the UI

- Card is the representation of a single task

- Labels are defined on a board level and can be assigned to any number of cards

## Global responses

### 400 Bad request

In case the request is invalid, e.g. because a parameter is missing or an invalid value has been transmitted, a 400 error will be returned:

```json
{
  "status": 400,
  "message": "title must be provided"
}
```

### 403 Permission denied

In any case a user doesn't have access to a requested entity, a 403 error will be returned:

```json
{
    "status": 403,
    "message": "Permission denied"
}
```

## Formats

### Date

Datetime values in request data need to be provided in ISO-8601. Example: 2020-01-20T09:52:43+00:00

## Headers

### If-Modified-Since

Some index endpoints support limiting the result set to entries that have been changed since the given time.
The supported date formats are:

* IMF-fixdate:                 `Sun, 03 Aug 2019 10:34:12 GMT`
* (obsolete) RFC 850:          `Sunday, 03-Aug-19 10:34:12 GMT`
* (obsolete) ANSI C asctime(): `Sun Aug  3 10:34:12 2019`

It is highly recommended to only use the IMF-fixdate format. Note that according to [RFC2616](https://tools.ietf.org/html/rfc2616#section-3.3) all HTTP date/time stamps MUST be represented in Greenwich Mean Time (GMT), without exception.

Example curl request:

```bash
curl -u admin:admin -X GET \
    'http://localhost:8000/index.php/apps/deck/api/v1.0/boards/2/stacks' \
    -H "OCS-APIRequest: true" \
    -H "If-Modified-Since: Mon, 05 Nov 2018 09:28:00 GMT"
```

### ETag

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

# Changelog

## API version 1.0

- Deck >=1.0.0: The maximum length of the card title has been extended from 100 to 255 characters
- Deck >=1.0.0: The API will now return a 400 Bad request response if the length limitation of a board, stack or card title is exceeded

## API version 1.1

This API version has become available with **Deck 1.3.0**.

- The maximum length of the card title has been extended from 100 to 255 characters
- The API will now return a 400 Bad request response if the length limitation of a board, stack or card title is exceeded
- The attachments API endpoints will return other attachment types than deck_file
  - Prior to Deck version v1.3.0 (API v1.0), attachments were stored within deck. For this type of attachments `deck_file` was used as the default type of attachments
  - Starting with Deck version 1.3.0 (API v1.1) files are stored within the users regular Nextcloud files and the type `file` has been introduced for that

## API version 1.2 (unreleased)

- Endpoints for the new import functionality have been added:
  - [GET /boards/import/getSystems - Import a board](#get-boardsimportgetsystems-import-a-board)
  - [GET /boards/import/config/system/{schema} - Import a board](#get-boardsimportconfigsystemschema-import-a-board)
  - [POST /boards/import - Import a board](#post-boardsimport-import-a-board)
- The `done` property was added to cards

# Endpoints

## Boards

### GET /boards - Get a list of boards

#### Headers

The board list endpoint supports setting an `If-Modified-Since` header to limit the results to entities that are changed after the provided time.

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| details   | Bool    | **Optional** Enhance boards with details about labels, stacks and users |

#### Response

##### 200 Success

Returns an array of board items

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

### POST /boards - Create a new board

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the new board, maximum length is limited to 100 characters |
| color     | String | The hexadecimal color of the new board (e.g. FF0000) |

```json
{
    "title": "Board title",
    "color": "ff0000"
}
```

#### Response

##### 200 Success

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

##### 403 Forbidden

A 403 response might be returned if the users ability to create new boards has been disabled by the administrator. For checking this before, see the `canCreateBoards` value in the [Nextcloud capabilties](./API-Nextcloud.md).

### GET /boards/{boardId} - Get board details

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

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

### PUT /boards/{boardId} - Update board details

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the board, maximum length is limited to 100 characters |
| color     | String | The hexadecimal color of the board (e.g. FF0000) |
| archived  | Bool   | Whether or not this board should be archived. |

```json
{
    "title": "Board title",
    "color": "ff0000",
    "archived": false
}
```

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/archive - Archive a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/unarchive - Unarchive a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

### DELETE /boards/{boardId} - Delete a board

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

### POST /boards/{boardId}/undo_delete - Restore a deleted board

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

### POST /boards/{boardId}/acl - Add new acl rule

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| type      | Integer | Type of the participant                              |
| participant     | String | The uid of the participant |
| permissionEdit  | Bool   | Setting if the participant has edit permissions |
| permissionShare  | Bool   | Setting if the participant has sharing permissions |
| permissionManage  | Bool   | Setting if the participant has management permissions |

##### Supported participant types:
- 0 User
- 1 Group
- 7 Circle

#### Response

##### 200 Success

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

### PUT /boards/{boardId}/acl/{aclId} - Update an acl rule

#### Request parameters

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| permissionEdit  | Bool   | Setting if the participant has edit permissions |
| permissionShare  | Bool   | Setting if the participant has sharing permissions |
| permissionManage  | Bool   | Setting if the participant has management permissions |

#### Response

##### 200 Success

### POST /boards/{boardId}/clone - Clone a board

Creates a copy of the board.

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| withCards  | Bool   | Setting if the cards should be copied (Default: false) |
| withAssignments  | Bool   | Setting if the card assignments should be cloned (Default: false) |
| withLabels  | Bool   | Setting if the card labels should be cloned (Default: false) |
| withDueDate  | Bool   | Setting if the card due dates should be cloned (Default: false) |
| moveCardsToLeftStack  | Bool   | Setting if all cards should be moved to the most left column (useful for To-Do / Doing / Done boards) (Default: false) |
| restoreArchivedCards  | Bool   | Setting if the archived cards should be unarchived (Default: false) |

#### Response

##### 200 Success

### DELETE /boards/{boardId}/acl/{aclId} - Delete an acl rule

#### Response

##### 200 Success

## Stacks

### GET /boards/{boardId}/stacks - Get stacks

#### Headers

The board list endpoint supports setting an `If-Modified-Since` header to limit the results to entities that are changed after the provided time.


#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

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

##### 200 Success

### GET /boards/{boardId}/stacks/archived - Get list of archived stacks

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

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

##### 200 Success

### GET /boards/{boardId}/stacks/{stackId} - Get stack details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

#### Response

##### 200 Success

### POST /boards/{boardId}/stacks - Create a new stack

#### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the new stack, maximum length is limited to 100 characters |
| order     | Integer | Order for sorting the stacks                         |

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId} - Update stack details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

#### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the stack, maximum length is limited to 100 characters |
| order     | Integer | Order for sorting the stacks                         |

#### Response

##### 200 Success

### DELETE /boards/{boardId}/stacks/{stackId} - Delete a stack

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

#### Response

##### 200 Success

## Cards

### GET /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Get card details

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

### POST /boards/{boardId}/stacks/{stackId}/cards - Create a new card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |

#### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the card, maximum length is limited to 255 characters |
| type      | String  | Type of the card (for later use) use 'plain' for now |
| order     | Integer | Order for sorting the stacks                         |
| description | String  | _(optional)_ The markdown description of the card  |
| duedate   | timestamp | _(optional)_ The duedate of the card or null       |

#### Response

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

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Update card details

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter   | Type            | Description                                                                                         |
|-------------|-----------------|-----------------------------------------------------------------------------------------------------|
| title       | String          | The title of the card, maximum length is limited to 255 characters                                  |
| description | String          | The markdown description of the card                                                                |
| type        | String          | Type of the card (for later use) use 'plain' for now                                                |
| owner       | String          | The user that owns the card                                                                         |
| order       | Integer         | Order for sorting the stacks                                                                        |
| duedate     | timestamp       | The ISO-8601 formatted duedate of the card or null                                                  |
| archived    | bool            | Whether the card is archived or not                                                                 |
| done        | timestamp\|null | The ISO-8601 formatted date when the card is marked as done (optional, null indicates undone state) |


```
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

#### Response

##### 200 Success

### DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Delete a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignLabel - Assign a label to a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| labelId   | Integer | The label id to assign to the card      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/removeLabel - Remove a label to a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| labelId   | Integer | The label id to remove to the card      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignUser - Assign a user to a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| userId    | String  | The user id to assign to the card       |

#### Response

##### 200 Success

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

##### 400 Bad request

```json
{
  "status": 400,
  "message": "The user is already assigned to the card"
}
```

The request can fail with a bad request response for the following reasons:
- Missing or wrongly formatted request parameters
- The user is already assigned to the card
- The user is not part of the board


### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/unassignUser - Unassign a user from a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| userId    | String  | The user id to unassign from the card   |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/reorder - Change the sorting order of a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                                                 |
| --------- | ------- | ----------------------------------------------------------- |
| order     | Integer | The position in the stack where the card should be moved to |
| stackId   | Integer | The id of the stack where the card should be moved to       |


#### Response

##### 200 Success

## Labels

### GET /boards/{boardId}/labels/{labelId} - Get label details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

#### Response

##### 200 Success

```json
{
  "title": "Abgeschlossen",
  "color": "31CC7C",
  "boardId": "2",
  "cardId": null,
  "id": 5
}
```

### POST /boards/{boardId}/labels - Create a new label

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |

#### Request data

```json
{
  "title": "Finished",
  "color": "31CC7C"
}
```

#### Response

##### 200 Success

### PUT /boards/{boardId}/labels/{labelId} - Update label details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |


#### Request data

```json
{
  "title": "Finished",
  "color": "31CC7C"
}
```

#### Response

##### 200 Success

### DELETE /boards/{boardId}/labels/{labelId} - Delete a label

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

#### Response

##### 200 Success

## Attachments

### GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Get a list of attachments

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

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

### GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Get the attachment file

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Response

##### 200 Success

### POST /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Upload an attachment

#### Request parameters

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| boardId   | Integer | The id of the board the attachment belongs to |
| stackId   | Integer | The id of the stack the attachment belongs to |
| cardId    | Integer | The id of the card the attachment belongs to  |

#### Request data

The request is performed as `multipart/form-data`.

| Parameter | Type    | Description                                                                                     |
| --------- | ------- | ----------------------------------------------------------------------------------------------- |
| type      | String  | The type of the attachement. Use `file` or `deck_file`.                                         |
| file      | Binary  | File data to add as an attachment together with the `filename` parameter according to RFC 7578. |

- Prior to Deck version v1.3.0 (API v1.0), attachments were stored within deck. For this type of attachments `deck_file` was used as the default type of attachments
- Starting with Deck version 1.3.0 (API v1.1) files are stored within the users regular Nextcloud files and the type `file` has been introduced for that

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Update an attachment

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Request data

The request is performed as `multipart/form-data`.

| Parameter | Type    | Description                                                                                     |
| --------- | ------- | ----------------------------------------------------------------------------------------------- |
| type      | String  | The type of the attachement. For now only `deck_file` is supported as an attachment type.       |
| file      | Binary  | File data to add as an attachment together with the `filename` parameter according to RFC 7578. |


#### Response

##### 200 Success

### DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Delete an attachment


#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}/restore - Resore a deleted attachment

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Response

##### 200 Success

### GET /boards/import/getSystems - Import a board

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| system       | Integer | The system name. Example: trello              |

#### Response

Make a request to see the json schema of system

```json
{
}
```

### GET /boards/import/config/system/{schema} - Import a board

#### Request parameters

#### Response

```json
[
  "trello"
]
```

### POST /boards/import - Import a board

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| system       | string  | The allowed name of system to import from     |
| config       | Object  | The config object  (JSON)                     |
| data         | Object  | The data object to import (JSON)              |

#### Response

##### 200 Success

# OCS API

The OCS endpoints under `/ocs/v2.php/apps/deck/api/v1.0/` are the supported Deck API. The legacy app API under `/index.php/apps/deck/api/v1.0/` still exists for backwards compatibility, but new integrations should use the OCS routes.

All OCS responses follow the standard OCS envelope:

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {}
  }
}
```

The value of `ocs.data` is the actual API payload for the request.

## Boards

Relative to the OCS base path `/ocs/v2.php/apps/deck/api/v1.0/`:

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| GET | `/boards` | none | `array<Board>` |
| GET | `/board/{boardId}` | `boardId: int` | `Board` or federated board payload |
| POST | `/boards` | `title: string`, `color: string` | `Board` |
| POST | `/boards/team` | `title: string`, `teamId: string`, `color?: string` | `Board` |
| POST | `/boards/{boardId}/acl` | `boardId: int`, `type: int`, `participant: string`, `permissionEdit: bool`, `permissionShare: bool`, `permissionManage: bool`, `remote?: string` | ACL object |

Notes:
- `boardId` is the Deck board identifier.
- Board responses include permissions and settings when available.
- External or federated boards use the same Deck object shape, but their data may be proxied from the remote instance.

## Stacks

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| GET | `/stacks/{boardId}` | `boardId: int` | `array<Stack>` |
| POST | `/stacks` | `title: string`, `boardId: int`, `order: int = 0` | `Stack` |
| PUT | `/stacks/{stackId}/done` | `stackId: int`, `boardId: int`, `isDone: bool` | empty success payload |
| DELETE | `/stacks/{stackId}/{boardId}` | `stackId: int`, `boardId?: int` | delete result |
| PUT | `/stacks/{stackId}/reorder` | `stackId: int`, `order: int`, `boardId?: int` | `array<Stack>` |

Notes:
- `order` is the visual ordering value for the stack.
- Stack creation defaults to `order = 0` unless a different value is supplied.

## Cards

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| POST | `/cards` | `title: string`, `stackId: int`, `boardId?: int`, `type?: string` (`plain` by default), `owner?: string`, `order?: int` (`999` by default), `description?: string`, `duedate?: mixed`, `startdate?: mixed`, `labels?: array`, `users?: array`, `color?: string` | `Card` |
| PUT | `/cards/{cardId}` | `cardId: int`, `title: string`, `stackId: int`, `type: string`, `order: int`, `description: string`, `duedate`, `deletedAt`, `boardId?: int`, `owner?: string\|array`, `archived?: mixed`, `startdate?: mixed` | `Card` |
| POST | `/cards/{cardId}/label/{labelId}` | `boardId?: int`, `cardId: int`, `labelId: int` | `Card` / assignment result |
| DELETE | `/cards/{cardId}/label/{labelId}` | `boardId?: int`, `cardId: int`, `labelId: int` | `Card` / assignment result |
| POST | `/cards/{cardId}/assign` | `boardId?: int`, `cardId: int`, `userId: string`, `type: int = 0` | assignment payload |
| PUT | `/cards/{cardId}/unassign` | `boardId?: int`, `cardId: int`, `userId: string`, `type: int = 0` | assignment payload |
| PUT | `/cards/{cardId}/reorder` | `cardId: int`, `stackId: int`, `order: int`, `boardId?: int` | `Card` |
| POST | `/cards/{cardId}/dependentCards/{dependentCardId}` | `cardId: int`, `dependentCardId: int`, `boardId?: int` | `Card` |
| DELETE | `/cards/{cardId}/dependentCards/{dependentCardId}` | `cardId: int`, `dependentCardId: int`, `boardId?: int` | `Card` |

Notes:
- `type` is the card type and defaults to `plain`.
- `done` and `color` are optional update fields and may be set to `null`.
- Assignments and label links are typically handled via the dedicated assign/remove endpoints rather than the card create request.

## Attachments

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| GET | `/cards/{cardId}/attachments` | `cardId: int`, `boardId?: int` | `array<Attachment>` |
| POST | `/cards/{cardId}/attachment` | `cardId: int`, `type: string`, `data?: string`, `boardId?: int` | `Attachment` |
| PUT | `/cards/{cardId}/attachments/{attachmentId}` | `cardId: int`, `attachmentId: int`, `data: string`, `type: string = file`, `boardId?: int` | `Attachment` |
| DELETE | `/cards/{cardId}/attachments/{type}:{attachmentId}` | `cardId: int`, `attachmentId: int`, `type: string = file`, `boardId?: int` | delete result |
| PUT | `/cards/{cardId}/attachments/{attachmentId}/restore` | `cardId: int`, `attachmentId: int`, `type: string = file`, `boardId?: int` | `Attachment` |

Notes:
- `type` is the attachment storage type, for example `file` or the legacy `deck_file` value.
- Attachment operations are only supported for local boards in the current controller implementation.

## Config

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| GET | `/config` | none | `array<string, mixed>` |
| POST | `/config/{key}` | `key: string`, `value: mixed` | stored value or `404` if the key does not exist |

The config payload contains global values and also board-specific values when the key is prefixed as `board:{boardId}:...`.

Examples of supported keys:
- `calendar`
- `cardDetailsInModal`
- `cardIdBadge`
- `notify-due` for board settings

## Comments

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| GET | `/cards/{cardId}/comments` | `cardId: int`, `limit: int = 20`, `offset: int = 0` | `array<Comment>` |
| POST | `/cards/{cardId}/comments` | `cardId: int`, `message: string`, `parentId: int = 0` | `Comment` |
| PUT | `/cards/{cardId}/comments/{commentId}` | `cardId: int`, `commentId: int`, `message: string` | `Comment` |
| DELETE | `/cards/{cardId}/comments/{commentId}` | `cardId: int`, `commentId: int` | delete result |

Notes:
- comments support mentions and optional replies via `parentId`.
- comment responses include `mentions`, and replies also include a nested `replyTo` object.

## Search and overview

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| GET | `/search` | `term: string`, `limit?: int`, `cursor?: int` | `array<CardDetails>` with `relatedBoard` and `relatedStack` |
| GET | `/overview/upcoming` | none | upcoming cards for the current user |

## Sessions

| Method | Route | Parameters | Response |
| --- | --- | --- | --- |
| PUT | `/session/create` | `boardId: int` | `{ token: string }` |
| POST | `/session/sync` | `boardId: int`, `token: string` | empty array or `404` if the token is invalid/expired |
| POST | `/session/close` | `boardId: int`, `token?: string` | empty success payload |

Notes:
- the session endpoint requires a valid board read permission before the session can be created or synced.
- `sync` returns `404` when the token is invalid or expired.
- `close` accepts an optional token and exits cleanly when it is not supplied.

The config endpoints are already summarized above. The global config is exposed via `GET /config`, and board-scoped values are written via `POST /config/{key}` with a JSON body like `{ "value": false }`. Keys such as `board:{boardId}:calendar` are resolved in the controller/service layer exactly as implemented in `ConfigController` and `ConfigService`.
