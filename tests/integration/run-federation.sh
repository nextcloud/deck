#!/usr/bin/env bash

# Federation integration test runner for Deck
# Sets up two Nextcloud instances (LOCAL + REMOTE) and runs behat federation tests

set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
OCC="${ROOT_DIR}/occ"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCENARIO_TO_RUN=$1

# Port configuration
LOCAL_PORT=8080
REMOTE_PORT=8280

# Check if the main instance is installed
INSTALLED=$($OCC status | grep installed: | cut -d " " -f 5)
if [ "$INSTALLED" != "true" ]; then
	echo "Nextcloud instance needs to be installed" >&2
	exit 1
fi

# ---- Set up the remote Nextcloud instance ----

if [ -z "$REMOTE_ROOT_DIR" ]; then
	# No external remote provided — create a local federated server
	REMOTE_ROOT_DIR="${ROOT_DIR}/data/tests-deck-federated-server"

	echo "Setting up local federated Nextcloud instance at ${REMOTE_ROOT_DIR}"

	rm -rf "${REMOTE_ROOT_DIR}"
	mkdir -p "${REMOTE_ROOT_DIR}"

	# Symlink all server files into the remote directory
	for item in "${ROOT_DIR}"/*; do
		name=$(basename "$item")
		if [ "$name" != "data" ] && [ "$name" != "config" ]; then
			ln -sf "$item" "${REMOTE_ROOT_DIR}/${name}"
		fi
	done

	mkdir -p "${REMOTE_ROOT_DIR}/data"
	mkdir -p "${REMOTE_ROOT_DIR}/config"

	# Copy base config
	cp "${ROOT_DIR}/config/config.php" "${REMOTE_ROOT_DIR}/config/config.php"

	# Install remote instance with SQLite
	# Use NEXTCLOUD_CONFIG_DIR so occ finds the remote config even when
	# server files are symlinked (which causes SERVERROOT to resolve to the original).
	REMOTE_OCC="NEXTCLOUD_CONFIG_DIR=${REMOTE_ROOT_DIR}/config php ${REMOTE_ROOT_DIR}/occ"
	eval $REMOTE_OCC maintenance:install \
		--database=sqlite \
		--admin-user=admin \
		--admin-pass=admin \
		--data-dir="${REMOTE_ROOT_DIR}/data"

	eval $REMOTE_OCC config:system:set hashing_default_password --value=true --type=boolean

	# Enable required apps on remote
	eval $REMOTE_OCC app:enable --force deck
else
	echo "Using external remote Nextcloud instance at ${REMOTE_ROOT_DIR}"
	REMOTE_OCC="NEXTCLOUD_CONFIG_DIR=${REMOTE_ROOT_DIR}/config php ${REMOTE_ROOT_DIR}/occ"
fi

MAIN_SERVER_CONFIG_DIR="${ROOT_DIR}/config"
REMOTE_SERVER_CONFIG_DIR="${REMOTE_ROOT_DIR}/config"

# ---- Install behat dependencies ----

# Server behat vendor
(
	cd "${ROOT_DIR}/vendor-bin/behat"
	composer install
)

# App test dependencies
(
	cd "${APP_DIR}/tests/integration"
	composer install
)

# ---- Enable deck and configure both instances ----

$OCC app:enable --force deck

# Configure LOCAL instance
$OCC config:system:set allow_local_remote_servers --value=true --type=boolean
$OCC config:system:set auth.bruteforce.protection.enabled --value=false --type=boolean
$OCC config:system:set ratelimit.protection.enabled --value=false --type=boolean
$OCC config:system:set debug --value=true --type=boolean
$OCC config:system:set hashing_default_password --value=true --type=boolean
$OCC config:app:set deck federationEnabled --value=yes
$OCC config:app:set files_sharing outgoing_server2server_share_enabled --value=yes
$OCC config:app:set files_sharing incoming_server2server_share_enabled --value=yes

# Configure REMOTE instance
eval $REMOTE_OCC config:system:set allow_local_remote_servers --value=true --type=boolean
eval $REMOTE_OCC config:system:set auth.bruteforce.protection.enabled --value=false --type=boolean
eval $REMOTE_OCC config:system:set ratelimit.protection.enabled --value=false --type=boolean
eval $REMOTE_OCC config:system:set debug --value=true --type=boolean
eval $REMOTE_OCC config:app:set deck federationEnabled --value=yes
eval $REMOTE_OCC config:app:set files_sharing outgoing_server2server_share_enabled --value=yes
eval $REMOTE_OCC config:app:set files_sharing incoming_server2server_share_enabled --value=yes

# Set trusted domains on both instances
$OCC config:system:set trusted_domains 0 --value="localhost:${LOCAL_PORT}"
eval $REMOTE_OCC config:system:set trusted_domains 0 --value="localhost:${REMOTE_PORT}"

# ---- Start PHP built-in servers ----

echo "Starting LOCAL server on port ${LOCAL_PORT}"
PHP_CLI_SERVER_WORKERS=3 php -S "localhost:${LOCAL_PORT}" -t "${ROOT_DIR}" &
LOCAL_PID=$!

echo "Starting REMOTE server on port ${REMOTE_PORT}"
NEXTCLOUD_CONFIG_DIR="${REMOTE_ROOT_DIR}/config" PHP_CLI_SERVER_WORKERS=3 php -S "localhost:${REMOTE_PORT}" -t "${ROOT_DIR}" &
REMOTE_PID=$!

# Wait for servers to start
sleep 2

# Verify servers are up
if ! curl -s "http://localhost:${LOCAL_PORT}/status.php" > /dev/null; then
	echo "LOCAL server failed to start" >&2
	kill -9 $LOCAL_PID $REMOTE_PID 2>/dev/null
	exit 1
fi

if ! curl -s "http://localhost:${REMOTE_PORT}/status.php" > /dev/null; then
	echo "REMOTE server failed to start" >&2
	kill -9 $LOCAL_PID $REMOTE_PID 2>/dev/null
	exit 1
fi

echo "Both servers are running"

# ---- Export environment variables for behat ----

export TEST_SERVER_URL="http://localhost:${LOCAL_PORT}/"
export TEST_REMOTE_URL="http://localhost:${REMOTE_PORT}/"
export NEXTCLOUD_HOST_ROOT_DIR="${ROOT_DIR}"
export NEXTCLOUD_HOST_CONFIG_DIR="${MAIN_SERVER_CONFIG_DIR}"
export NEXTCLOUD_REMOTE_ROOT_DIR="${REMOTE_ROOT_DIR}"
export NEXTCLOUD_REMOTE_CONFIG_DIR="${REMOTE_SERVER_CONFIG_DIR}"

# ---- Run behat federation tests ----

cd "${APP_DIR}/tests/integration"

BEHAT_SUITE="federation"
if [ -n "$SCENARIO_TO_RUN" ]; then
	vendor/bin/behat --colors --suite="${BEHAT_SUITE}" "$SCENARIO_TO_RUN"
else
	vendor/bin/behat --colors --suite="${BEHAT_SUITE}"
fi
RESULT=$?

# ---- Cleanup ----

kill -9 $LOCAL_PID $REMOTE_PID 2>/dev/null

echo "Federation tests: Exit code: $RESULT"
exit $RESULT
