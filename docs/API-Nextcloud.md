# Nextcloud APIs

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
