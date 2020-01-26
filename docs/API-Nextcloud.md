# Nextcloud APIs

## Capabilities

The [Nextcloud Capabilities API](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-api-overview.html#capabilities-api) provides the following information for the Deck app:

- `version` Current version of the Deck app running
- `canCreateBoards` Ability of the current user to create new boards for themselves

```
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "capabilities": {
        "deck": {
          "version": "0.8.0",
          "canCreateBoards": true
        },
      }
    }
  }
}
```



## Available sharees

When sharing a board to a user, group or circle, the possible sharees can be obtained though the files_sharing API.

API endpoint: https://nextcloud.local/index.php/apps/files_sharing/api/v1/sharees

### Parameters
- format: **The response format**
- perPage: **Limit response number**
- itemType: **List of types. Currently supported are**
  - 0 user
  - 1 group
  - 7 circle


## Comments

Comments are stored using the Nextcloud Comments API. You can use the WebDAV endpoint of Nextcloud to fetch, update and delete comments.

### List comments

PROPFIND`remote.php/dav/comments/deckCard/{cardId}`

### Create comment

POST `remote.php/dav/comments/deckCard/{cardId}`

### Update comment

PROPPATCH `remote.php/dav/comments/deckCard/{cardId}/{commentId}`

### Delete comment

DELETE `remote.php/dav/comments/deckCard/{cardId}/{commentId}`

## Activity

The Nextcloud activity app provides an API to fetch activities filtered for deck: [Activity app API documentation](https://github.com/nextcloud/activity/blob/master/docs/endpoint-v2.md)

The deck app offers a filter `deck` to only request activity events that are relevant.
