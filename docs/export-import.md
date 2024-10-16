<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
## Export

Deck currently supports exporting all boards a user owns in a single JSON file. The format is based on the database schema that deck uses. It can be used to re-import boards on the same or other instances.

The export currently has some kown limitations in terms of specific data not included:
- Activity information
- File attachments to deck cards
- Comments
-
```
occ deck:export userid > userid-deck-export.json
```
(userid = username you see in admin useraccounts page)

## Import boards

Importing can be done using the API or the `occ` `deck:import` command.

It is possible to import from the following sources:

### Deck JSON

A json file that has been obtained from the above described `occ deck:export [userid]  > userid-deck-export.json` command can be imported.

```
occ deck:import userid-deck-export.json
```

You will be asked to provide a path to a config file.

To know what to put in there:
- Have a look at your userid-deck-export.json
 - fairly at the top you will see "uid" with a username.
  - search for some more "uid" till you find all the usernames involved and note them.
 - search for "acl"
  - in there there are "uid" of groups note them too
    
In case you are importing from a different instance you must provide custom user id mapping in case users have different identifiers.

create a config file e.g `deck-import-config-file-userid.json` and ajust the content of this example as descibed above.
Userids on new instance can be seen in the admin useraccounts page.
```
{
    "owner": "useridofnewownderofallboards",
    "uidRelation": {
        "userid1onoldinstance": "userid1onnewinstance",
	"userid2onoldinstance": "userid2onnewinstance",
	"groupid1onoldinstance": "groupid1onnewinstance"

    }
}
```
after you hit enter everything will be imported.


Additional info:
- If you export a users boards, all boards that the user has access to will be exported. (also the onws shared to that user)


#### Trello JSON

Limitations:
* Comments with more than 1000 characters are placed as attached files to the card.

Steps:
* Create the data file
	* Access Trello
	* go to the board you want to export
	* Follow the steps in [Trello documentation](https://help.trello.com/article/747-exporting-data-from-trello-1) and export as JSON
* Create the configuration file
* Execute the import informing the import file path, data file and source as `Trello JSON`

Create the configuration file respecting the [JSON Schema](https://github.com/nextcloud/deck/blob/main/lib/Service/Importer/fixtures/config-trelloJson-schema.json) for import `Trello JSON`

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

**Limitations**:

Importing from a JSON file imports up to 1000 actions. To find out how many actions the board to be imported has, identify how many actions the JSON has.

#### Trello API

Import using API is recommended for boards with more than 1000 actions.

Trello makes it possible to attach links to a card. Deck does not have this feature. Attachments and attachment links are added in a markdown table at the end of the description for every imported card that has attachments in Trello.

* Get the API Key and API Token [here](https://developer.atlassian.com/cloud/trello/guides/rest-api/api-introduction/#authentication-and-authorization)
* Get the ID of the board you want to import by making a request to:
  https://api.trello.com/1/members/me/boards?key={yourKey}&token={yourToken}&fields=id,name

  This ID you will use in the configuration file in the `board` property
* Create the configuration file

Create the configuration file respecting the [JSON Schema](https://github.com/nextcloud/deck/blob/main/lib/Service/Importer/fixtures/config-trelloApi-schema.json) for import `Trello JSON`

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
