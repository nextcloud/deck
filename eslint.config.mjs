/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { recommended } from '@nextcloud/eslint-config'
import pluginCypress from 'eslint-plugin-cypress/flat'

export default [
	...recommended,
	{
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param-description': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/check-param-names': 'off',
			'jsdoc/no-undefined-types': 'off',
			'jsdoc/require-property-description': 'off',
			'@typescript-eslint/no-unused-vars': 'off',
			'vue/multi-word-component-names': 'off',
		},
	},
	{
		...pluginCypress.configs.recommended,
		files: ['cypress/**/*.js'],
	},
]
