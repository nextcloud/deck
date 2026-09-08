<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
## Export

Deck supports exporting boards to a single JSON file. The format is based on the database schema that Deck uses. It can be used to re-import boards on the same or other instances.

The export is a complete representation of a board and contains:
- lists, including which one is configured as the done column
- cards, including archived ones, with their card ID and list ID
- the completion state and the date a card was completed
- due date, start date, creation date and last modification date
- card colour and card type
- dependencies between cards
- labels, assigned users, comments and file attachments

On import, card dependencies are remapped to the newly created cards. A
dependency that points at a card outside the import - one on another board, or
one skipped because archived cards were deselected - is dropped instead of
leaving a dangling reference behind.

Dates are exported as ISO 8601 including the UTC offset, so they keep pointing at
the same point in time no matter which timezone imports or reads them.

Known limitations, this data is not part of an export:
- Activity information
- Cards in the trash bin

### From the web interface

Open the board menu, choose *Export board* and pick a format:

- **JSON** – the complete board, suited for importing back into Deck.
- **CSV** – one row per card with the card ID, list ID, list name, tags, assigned
  users, archived and completion state, all date fields and the comment and
  attachment counts. Suited for reporting and for external tools such as
  spreadsheets or BI tools. A CSV cannot be imported back into Deck.

The CSV follows RFC 4180: comma separated, every field quoted, inner quotes
doubled, and UTF-8 with a byte order mark. A separator, a semicolon or the line
breaks of a markdown description can therefore appear inside a cell without
breaking the file.

Exports are machine readable output, so nothing in them is translated. The column
headers are always English and archived and completed are written as `1` and `0`,
which means a report keeps working no matter which interface language the
exporting user has. The JSON export behaves the same way, its keys being the
English property names.

Attachment contents are never part of a CSV, but the attachment count is, so the
column stays meaningful.

Attachment contents are embedded in the JSON export, which can make the file
large. They can be left out in the export dialog, at the cost of an export that
no longer restores the board completely.

### From the command line

```
occ deck:export userid > userid-deck-export.json
```
*(`userid` = username as seen in the admin user accounts page)*

Pass `--no-attachments` to leave the attachment contents out of the export.

## Import Boards

Importing can be done from the web interface, using the API or the `occ`
`deck:import` command.

When importing a board through the web interface, a dialog offers to select which
parts of the file to restore: cards, archived cards, completion state, due and
start dates, tags, assigned users, comments, attachments and sharing. Lists and
labels are always created, so deselecting cards results in an empty copy of the
board that can be used as a template.

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
