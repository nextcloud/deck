/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateFilePath } from '@nextcloud/router'

// Vazirmatn font + RTL support for Persian/Arabic text. Loaded here because
// every entry point (main app, dashboard, reference, talk, calendar,
// collections) imports this module, so the font applies everywhere Deck runs.
import './css/fonts.scss'

// Auto-detect text direction (RTL for Persian/Arabic) in editable fields and
// in elements that display user content. Loaded here so it applies across
// every Deck entry point.
import './helpers/autoTextDirection.js'

// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)

if (!process.env.WEBPACK_SERVE) {
	// eslint-disable-next-line
	__webpack_public_path__ = generateFilePath('deck', '', 'js/')
} else {
	// eslint-disable-next-line
	__webpack_public_path__ = 'http://127.0.0.1:3000/'
}
