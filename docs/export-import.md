<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
## Export

Deck currently supports exporting all boards a user owns in a single JSON file. The format is based on the database schema that Deck uses. It can be used to re-import boards on the same or other instances.

The export currently has some known limitations in terms of specific data not included:
- Activity information
- File attachments to Deck cards
- Comments

```
occ deck:export userid > userid-deck-export.json
```
*(`userid` = username as seen in the admin user accounts page)*

## Import Boards

Importing can be done using the API or the `occ` `deck:import` command.

It is possible to import from the following sources:

### Deck JSON

A JSON file that has been obtained from the above-described `occ deck:export [userid] > userid-deck-export.json` command can be imported.

```
occ deck:import userid-deck-export.json
```

You will be asked to provide a path to a config file.

To know what to put in there:
- Have a look at your `userid-deck-export.json`
  - Near the top, you will see `"uid"` with a username.
  - Search for additional `"uid"` entries to find all the usernames involved and note them.
  - Search for `"acl"`, where `"uid"`s of groups are also present; note them too.
    
If you are importing from a different instance, you must provide custom user ID mapping in case users have different identifiers.

Create a config file, e.g., `deck-import-config-file-userid.json`, and adjust the content of this example as described above. User IDs on the new instance can be seen in the admin user accounts page.

```json
{
    "owner": "useridofnewownerofallboards",
    "uidRelation": {
        "userid1onoldinstance": "userid1onnewinstance",
        "userid2onoldinstance": "userid2onnewinstance",
        "groupid1onoldinstance": "groupid1onnewinstance"
    }
}
```

After pressing enter, everything will be imported.

Additional info:
- If you export a user’s boards, all boards that the user has access to will be exported (including those shared with that user).

#### Trello JSON

**Limitations:**
* Comments with more than 1000 characters are placed as attached files to the card.

**Steps:**
1. Create the data file:
   * Access Trello.
   * Go to the board you want to export.
   * Follow the steps in [Trello documentation](https://help.trello.com/article/747-exporting-data-from-trello-1) and export as JSON.
2. Create the configuration file.
3. Execute the import, specifying the import file path, data file, and source as `Trello JSON`.

Create the configuration file respecting the [JSON Schema](https://github.com/nextcloud/deck/blob/main/lib/Service/Importer/fixtures/config-trelloJson-schema.json) for importing `Trello JSON`.

Example configuration file:

```json
{
    "owner": "admin",
    "color": "0800fd",
    "uidRelation": {
        "johndoe": "johndoe"
    }
}
```

**Additional Limitations**:
* Importing from a JSON file imports up to 1000 actions. To find out how many actions the board to be imported has, check the number of actions in the JSON.

#### Trello API

Importing via API is recommended for boards with more than 1000 actions. Trello allows attaching links to a card, but Deck does not support this feature. Attachment links are instead added in a markdown table at the end of the description for each imported card.

1. Get the API Key and Token [here](https://developer.atlassian.com/cloud/trello/guides/rest-api/api-introduction/#authentication-and-authorization).
2. Obtain the ID of the board you want to import by making a request to:
   ```
   https://api.trello.com/1/members/me/boards?key={yourKey}&token={yourToken}&fields=id,name
   ```
3. Create the configuration file, ensuring it follows the [JSON Schema](https://github.com/nextcloud/deck/blob/main/lib/Service/Importer/fixtures/config-trelloApi-schema.json) for `Trello JSON`.

Example configuration file:

```json
{
    "owner": "admin",
    "color": "0800fd",
    "api": {
        "key": "0cc175b9c0f1b6a831c399e269772661",
        "token": "92eb5ffee6ae2fec3ad71c777531578f4a8a08f09d37b73795649038408b5f33"
    },
    "board": "8277e0910d750195b4487976",
    "uidRelation": {
        "johndoe": "johndoe"
    }
}
```
