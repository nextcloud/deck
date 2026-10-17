<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Deck

[![CodeCov](https://codecov.io/github/nextcloud/deck/coverage.svg?branch=main)](https://codecov.io/github/nextcloud/deck) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nextcloud/deck/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nextcloud/deck/?branch=main) [![#nextcloud-deck](https://img.shields.io/badge/IRC-%23nextcloud--deck%20on%20freenode-blue.svg)](https://webchat.freenode.net/?channels=nextcloud-deck) [![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/deck)](https://api.reuse.software/info/github.com/nextcloud/deck)


Deck is a kanban style organization tool aimed at personal planning and project organization for teams integrated with Nextcloud.

- Add your tasks to cards and put them in order
- Write down additional notes in markdown
- Assign labels for even better organization
- Share with your team, friends or family
- Integrates with the [Circles](https://github.com/nextcloud/circles) app!
- Attach files and embed them in your markdown description
- Discuss with your team using comments
- Keep track of changes in the activity stream
- Get your project organized

![Deck - Manage cards on your board](http://download.bitgrid.net/nextcloud/deck/screenshots/1.0/Deck-2.png)

### Mobile apps

- [Nextcloud Deck app for Android](https://github.com/stefan-niedermann/nextcloud-deck) - It is available in [F-Droid](https://f-droid.org/de/packages/it.niedermann.nextcloud.deck/) and the [Google Play Store](https://play.google.com/store/apps/details?id=it.niedermann.nextcloud.deck.play)
- [Nextcloud Deck app for iOS](https://github.com/holger-dev/nextdeck) - It is available in [Apple App store](https://apps.apple.com/de/app/next-deck/id6752478755)

### 3rd-Party Integrations

- [trello-to-deck](https://github.com/maxammann/trello-to-deck) - Migrates cards from Trello
- [mail2deck](https://github.com/newroco/mail2deck) - Provides an "email in" solution
- [A-deck](https://github.com/leoossa/A-deck) - Chrome Extension that allows to create new card in selected stack based on current tab
- [QOwnNotes](https://github.com/pbek/QOwnNotes) - Quickly creates cards and links to them in Markdown notes

## Installation/Update

The app can be installed through the app store within Nextcloud. You can also download the latest release from the [release page](https://github.com/nextcloud-releases/deck/releases).

## Performance limitations

Deck is not yet ready for intensive usage.
A lot of database queries are generated when the number of boards, cards and attachments is high.
For example, a user having access to 13 boards, with each board having on average 100 cards,
and each card having on average 5 attachments,
would generate 6500 database queries when doing the file related queries
which would increase the page loading time significantly.

Improvements on Nextcloud server and Deck itself will improve the situation.

## Embedding a Deck board in another Nextcloud app

Deck provides an opt-in `nextcloud-deck-board` custom element implemented as a
Vue single-file component and registered with Vue's `defineCustomElement()`.
It reuses the
existing board UI without Deck's app navigation or browser-history router.
The normal Deck app and the reference widgets keep their existing entry points.
The element is intended for authenticated, same-instance Nextcloud pages.
Existing Deck permissions apply to every API request; passing a board ID does
not grant access or establish that the board belongs to a particular team.
The host app must validate its own team-resource context.

Before rendering the host page, check that Deck is enabled for the current user
and dispatch its loading event:

```php
$eventDispatcher->dispatchTyped(new \OCA\Deck\Event\LoadBoardWebComponentEvent());
```

This loads `deck-boardComponent`, the Deck styles, and the attachment/editor
integrations. Do not load `deck-main` in the host page. Hosts supporting older
Deck versions must check that the event class exists and show an explicit
unavailable state when this integration is not supported.

```html
<nextcloud-deck-board board-id="42" style="display: block; height: 100%">
</nextcloud-deck-board>
```

Wait for `customElements.whenDefined('nextcloud-deck-board')` before relying on
the component. Vue hosts must configure this tag as a custom element. Give the
element a bounded height; the board handles its own scrolling. The component
uses light DOM to work with Nextcloud's shared styles, dialogs and viewer.
When binding IDs from Vue, use `:board-id.attr="boardId"` and
`:card-id.attr="cardId"` so a `null` card ID removes the attribute rather than
being coerced into an empty or zero-valued DOM property.
Vue manages the element's rendering, reactive attributes and lifecycle. Each
element still mounts an isolated Deck app with its own stores and memory router;
removing an element disposes that app after Vue's disconnection tick. Moving an
element within the same document preserves its mounted instance.

- `board-id`: required positive safe integer. Changing it disposes the previous
  instance and opens the new board with independent state.
- `card-id`: optional positive safe integer. Set it to open a card, or remove it
  to close card details. Cards must belong to the displayed board. Opening
  archived card details after the board has loaded preserves the current list
  view and its active cards.
- `deck-ready`: the board and its lists have loaded; `detail` contains `boardId`.
- `deck-error`: loading, navigation, or runtime failed; `detail` contains
  `boardId`, `message`, and `status` (HTTP status when available).
- `deck-navigation`: user navigation requested a board/card; `detail` contains
  `boardId`, `cardId` (or `null`), and `tabId` (or `null`).

Events bubble. The component never changes the host's URL. The host owns its
route and active navigation styling, and should mirror card navigation into
`card-id`. Cross-board links emit a navigation request but do not change the
embedded board; the host must check its context before changing `board-id`.
Removing the element disposes its Vue app, stores, live-update subscriptions
and session. The embedded sessions do not change global Axios session headers.

Board management/sharing sidebars and global Deck keyboard shortcuts are not
part of this embed entry point. Board-level filtering, view modes, lists, card
editing and card-detail tabs use the existing Deck components.

## Developing

There are multiple ways to develop on Deck. As you will need a Nextcloud server running, the individual options are described below.

### General build instructions

General build instructions for the app itself are the same for all options.

To build you will need to have [Node.js](https://nodejs.org/en/) and [Composer](https://getcomposer.org/) installed.

- Install PHP dependencies: `composer install --no-dev`
- Install JS dependencies: `npm ci`
- Build JavaScript for the frontend
    - Development build `npm run dev`
    - Watch for changes `npm run watch`
    - Production build `npm run build`

### Faster frontend developing with HMR

You can enable HMR (Hot module replacement) to avoid page reloads when working on the frontend:

1. ☑️ Install and enable [`hmr_enabler` app](https://github.com/nextcloud/hmr_enabler)
2. 🏁 Run `npm run serve`
3. 🌍 Open the normal Nextcloud server URL (not the URL given by above command)

### GitHub Codespaces / VS Code devcontainer

- Open code spaces or the repository in VS Code to start the dev container
- The container will automatically install all dependencies and build the app
- Nextcloud will be installed from the master development branch and be available on a port exposed by the container

### Docker: Simple app development container

- Fork the app
- Clone the repository: `git clone https://github.com/nextcloud/deck.git`
- Go into deck directory: `cd deck`
- Build the app as described in the general build instructions
- Run Nextcloud development container and mount the apps source code into it

```
docker run --rm \
    -p 8080:80 \
    -v $PWD:/var/www/html/apps-extra/deck \
    ghcr.io/juliushaertl/nextcloud-dev-php81:latest
```

### Full Nextcloud development environment

You need to setup a [development environment](https://docs.nextcloud.com/server/latest/developer_manual//getting_started/devenv.html) of the current Nextcloud version. You can also alternatively install & run the [nextcloud docker container](https://github.com/juliushaertl/nextcloud-docker-dev).
After the finished installation, you can clone the deck project directly in the `/[nextcloud-docker-dev-dir]/workspace/server/apps/` folder.

### Running tests
Run the frontend logic regression tests with `npm test -- --runInBand`.
The embedded board's browser lifecycle, navigation, archive and undo behavior,
and the existing non-interactive board reference entry point are tested in the Cypress E2E
suite. With a running Nextcloud test instance (using the existing `admin`/`admin`
test credentials), build the frontend and run:

```sh
npm run dev
npx cypress run --spec cypress/e2e/embeddedBoard.js
```

The default test URL is `http://nextcloud.local/index.php`; set `CYPRESS_baseUrl`
to use another instance. The spec creates its own user and boards and deletes
the user afterwards. It swaps the normal Deck entry bundle for the embed or
reference bundle in the browser, reusing the authenticated page's integrations
without mounting the full Deck app.

Cypress runs in the existing E2E CI workflow. It does not collect JavaScript
coverage; `npm run test:coverage -- --runInBand` measures the Jest logic tests
only. The Codecov configuration enables project and patch coverage checks,
but does not make Cypress E2E assertions a JavaScript coverage report.

You can use the provided Makefile to run all tests by using:

    make test

#### Running behat integration tests

Within `tests/integration/` run `composer install` and then choose one of the two options:
- Run tests with a local php server: `bash run.sh`
- Run against an existing Nextcloud installation: `BEHAT_SERVER_URL=http://nextcloud.local ./vendor/bin/behat --colors features/decks.feature`

### Documentation

The documentation for our REST API can be found at https://deck.readthedocs.io/en/latest/API/

## Contribution Guidelines

Please read the [Code of Conduct](https://nextcloud.com/community/code-of-conduct/). This document offers some guidance to ensure Nextcloud participants can cooperate effectively in a positive and inspiring atmosphere, and to explain how together we can strengthen and support each other.

For more information please review the [guidelines for contributing](https://github.com/nextcloud/server/blob/master/.github/CONTRIBUTING.md) to this repository.

### Apply a license

All contributions to this repository are considered to be licensed under
the GNU AGPLv3 or any later version.

Contributors to the Deck app retain their copyright. Therefore we recommend
to add following line to the header of a file, if you changed it substantially:

```
@copyright Copyright (c) <year>, <your name> (<your email address>)
```

For further information on how to add or update the license header correctly please have a look at [our licensing HowTo][applyalicense].

### Sign your work

We use the Developer Certificate of Origin (DCO) as a additional safeguard
for the Nextcloud project. This is a well established and widely used
mechanism to assure contributors have confirmed their right to license
their contribution under the project's license.
Please read [developer-certificate-of-origin][dcofile].
If you can certify it, then just add a line to every git commit message:

````
  Signed-off-by: Random J Developer <random@developer.example.org>
````

Use your real name (sorry, no pseudonyms or anonymous contributions).
If you set your `user.name` and `user.email` git configs, you can sign your
commit automatically with `git commit -s`. You can also use git [aliases](https://git-scm.com/book/tr/v2/Git-Basics-Git-Aliases)
like `git config --global alias.ci 'commit -s'`. Now you can commit with
`git ci` and the commit will be signed.

[dcofile]: https://github.com/nextcloud/server/blob/master/contribute/developer-certificate-of-origin
[applyalicense]: https://github.com/nextcloud/server/blob/master/contribute/HowToApplyALicense.md
