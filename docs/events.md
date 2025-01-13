<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
## Webhook Events

Currently, Deck sends the following events that can be received by the [`webhook_listener`](https://docs.nextcloud.com/server/latest/admin_manual/webhook_listeners/index.html) app for Nextcloud Flow automations:

### `CardCreatedEvent`

Fired when a new card is created. Payload:

```text
{
  "title": string,
  "description": string,
  "boardId": int,
  "stackId": int,
  "lastModified": string,
  "createdAt": string
  "labels": [
    {
      "id": int,
      "title": string
    },
  ],
  "assignedUsers": string[],
  "order": int,
  "archived": bool,
  "commentsUnread": int,
  "commentsCount": int,
  "owner": string | null,
  "lastEditor": string | null,
  "duedate": string | null,
  "doneAt": string | null,
  "deletedAt": string | null
}
```

Note: All timestamps are in ISO8601 format: `2025-01-11T12:34:56+00:00`

### `CardUpdatedEvent`

Fired when a card is changed. Contains the values before and after the update. Payload:

```text
{
    "before": {
        //...same format as CardCreatedEvent...
    },
    "after": {
        //...same format as CardCreatedEvent...
    }
}
```

### `CardDeletedEvent`

Fired when a card is deleted. Payload:

```text
{
    //...same as CardCreatedEvent...
}
```
