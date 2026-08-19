/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateFilePath } from '@nextcloud/router'

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)

if (!process.env.WEBPACK_SERVE) {
	// eslint-disable-next-line
	__webpack_public_path__ = generateFilePath('deck', '', 'js/')
} else {
	// eslint-disable-next-line
	__webpack_public_path__ = 'http://127.0.0.1:3000/'
}
