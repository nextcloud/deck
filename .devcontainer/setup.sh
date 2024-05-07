#!/bin/bash

# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: CC0-1.0

(
    cd /tmp && /usr/local/bin/bootstrap.sh apache2ctl start
)

composer install --no-dev
npm ci
npm run dev