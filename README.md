# Deck

[![Build Status](https://travis-ci.org/juliushaertl/deck.svg?branch=master)](https://travis-ci.org/juliushaertl/deck) [![CodeCov](https://codecov.io/github/juliushaertl/deck/coverage.svg?branch=master)](https://codecov.io/github/juliushaertl/deck) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/juliushaertl/deck/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/juliushaertl/deck/?branch=master) (https://raw.githubusercontent.com/juliushaertl/deck/master/LICENSE) [![Dependency Status](https://www.versioneye.com/user/projects/58908fc0a23e810038c34e0a/badge.svg)](https://www.versioneye.com/user/projects/58908fc0a23e810038c34e0a)

Deck is a kanban style project and personal management tool integrated with Nextcloud.

- :inbox_tray: Add your tasks to cards and put them in order
- :page_facing_up: Write down additional notes in markdown
- :busts_in_silhouette: Share with your team, friends or family
- :rocket: Get your project organized

![Deck - Manage cards on your board](https://bitgrid.net/~jus/deck.png)

:boom: This is still alpha software: it may not be stable enough for production 

### Planned features

- :file_folder: Attach files directly from your Nextcloud
- :earth_africa: Share boards with the public
- :calendar: Integration with Nextcloud calendar and other apps
- :speech_balloon: Comments integration
- :exclamation: Checkout the project milestones for more ...

## Installation/Update

This app is supposed to work on Nextcloud version 11 or later.

### Install latest release

Grab the latest release over here, extract it to your Nextcloud apps folder and enable the app.

### Install from git 

If you want to run the latest development version from git source, you need to clone the repo to your apps folder:

```
git clone https://github.com/juliushaertl/deck.git
cd deck
make install-deps
make
```

Please make sure you have installed the following dependencies: `make, which, tar, npm, curl`

## Developing

### PHP

Nothing to prepare, just dig into the code.

### JavaScript

Make sure you have installed the dependencies with ```make install-deps```. After that you can run ```make``` to build the javascript code once or run ```make watch``` to run in on every file change.

### Running tests
You can use the provided Makefile to run all tests by using:

    make test
