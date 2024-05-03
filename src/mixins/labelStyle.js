/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Color from './color.js'

export default {
	mixins: [Color],
	computed: {
		labelStyle() {
			return (label) => {
				return {
					backgroundColor: '#' + label.color,
					color: this.textColor(label.color),
				}
			}
		},
	},
}
