# Deck REST API

The REST API provides access for authenticated users to their data inside the Deck app.

## Prequisited

All requests require a `OCS-APIRequest` HTTP header to be set to `true` and a `Content-Type` of `application/json`. The API is located at https://nextcloud.local/index.php/apps/deck/api/v1.0

### Naming

- Board is the the project like grouping of tasks that can be shared to different users and groups

- Stack is the grouping of cards which is rendered in vertical columns in the UI

- Card is the representation of a single task

- Labels are defined on a board level and can be assigned to any number of cards

### Global responses

#### 403 Permission denied

In any case a user doesn't have access to a requested entity, a 403 error will be returned:

```json
{
    "status": 403,
    "message": "Permission denied"
}
```

## Endpoints

### Boards

#### GET /boards - Get a list of boards

##### Response

###### 200 Success

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
        "id": 10
    }
]
```

#### POST /boards - Create a new board

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the new board                           |
| color     | String | The hexadecimal color of the new board (e.g. FF0000) |

```json
{
    "title": "Board title",
    "color": "ff0000"
}
```

##### Response

###### 200 Success

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
    "id": 10
}
```

#### GET /boards/{boardId} - Get board details

##### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

###### 200 Success

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

#### PUT /boards/{boardId} - Update board details

##### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the new board                           |
| color     | String | The hexadecimal color of the new board (e.g. FF0000) |
| archived  | Bool   | The hexadecimal color of the new board (e.g. FF0000) |

```json
{
    "title": "Board title",
    "color": "ff0000",
      "archived: false
}
```

##### Response

###### 200 Success

#### DELETE /boards/{boardId} - Delete a board

##### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

###### 200 Success

#### POST /boards/{boardId}/undo_delete - Restore a deleted board

##### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

###### 200 Success

### Stacks

#### GET /board/{boardId}/stacks - Get stacks

##### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

###### 200 Success

#### GET /board/{boardId}/stacks/archived - Get list of archived stacks

##### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

###### 200 Success

#### GET /board/{boardId}/stacks/{stackId} - Get stack details

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The if of the stack                      |

##### Response

###### 200 Success

#### POST /board/{boardId}/stacks - Create a new stack

##### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId} - Update stack details

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The if of the stack                      |

##### Response

###### 200 Success

#### DELETE /board/{boardId}/stacks/{stackId} - Delete a stack

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The if of the stack                      |

##### Response

###### 200 Success

### Cards

#### GET /board/{boardId}/stacks/{stackId}/cards/{cardId} - Get card details

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### POST /board/{boardId}/stacks/{stackId}/cards - Create a new card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId} - Update card details

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### DELETE /board/{boardId}/stacks/{stackId}/cards/{cardId} - Delete a card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/assignLabel - Assign a label to a card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/removeLabel - Remove a label to a card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/assignUser - Assign a user to a card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/unassignUser - Assign a user to a card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/reorder - Change the sorting order of a card

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

### Labels

#### GET /board/{boardId}/labels/{labelId} - Get label details

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

##### Response

###### 200 Success

#### POST /board/{boardId}/labels - Create a new label

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |

##### Response

###### 200 Success

#### PUT /board/{boardId}/labels/{labelId} - Update label details

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

##### Response

###### 200 Success

#### DELETE /board/{boardId}/labels/{labelId} - Delete a label

##### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

##### Response

###### 200 Success

### Attachments

#### GET /board/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Get a list of attachments

##### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The if of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

##### Response

###### 200 Success

#### GET /board/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Get the attachment file

##### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The if of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

##### Response

###### 200 Success

#### POST /board/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Upload an attachment

##### Request parameters

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| boardId   | Integer | The id of the board the attachment belongs to |
| stackId   | Integer | The if of the stack the attachment belongs to |
| cardId    | Integer | The id of the card the attachment belongs to  |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Update an attachment

##### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The if of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

##### Response

###### 200 Success

#### DELETE /board/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Delete an attachment

##### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The if of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

##### Response

###### 200 Success

#### PUT /board/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}/restore - Resore a deleted attachment

##### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The if of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

##### Response

###### 200 Success

## Other APIs

### Comments

Comments are stored using the Nextcloud Comments API. You can use the WebDAV endpoint of Nextcloud to fetch, update and delete comments.

#### List comments

PROPFIND`remote.php/dav/comments/deckCard/{cardId}`

#### Create comment

POST `remote.php/dav/comments/deckCard/{cardId}`

#### Update comment

PROPPATCH `remote.php/dav/comments/deckCard/{cardId}/{commentId}`

#### Delete comment

DELETE `remote.php/dav/comments/deckCard/{cardId}/{commentId}`

### Activity

The Nextcloud activity app provides an API to fetch activities filtered for deck:

https://github.com/nextcloud/activity/blob/master/docs/endpoint-v2.md

The deck app offers a filter `deck` to only request activity events that are relevant.
