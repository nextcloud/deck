/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import chroma from 'chroma-js'

export default {
	methods: {
		textColor(hex) {
			return chroma(hex ?? 'ffffff').get('lab.l') > 50 ? '#000000' : '#ffffff'
		},
		colorIsValid(hex) {
			const re = /[A-Fa-f0-9]{6}/
			if (re.test(hex)) {
				return true
			}
			return false
		},
		randomColor() {
			return Math.floor(Math.random() * (0xffffff + 1)).toString(16).padStart(6, '0')
		},
	},
}
