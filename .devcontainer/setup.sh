#!/bin/bash

(
    cd /tmp && /usr/local/bin/bootstrap.sh apache2ctl start
)

composer install --no-dev
npm ci
npm run dev