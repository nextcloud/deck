/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

export default {
	methods: {
		hexToRgb(hex) {
			const result = /^#?([A-Fa-f\d]{2})([A-Fa-f\d]{2})([A-Fa-f\d]{2})$/i.exec(hex)
			if (result) {
				return {
					r: parseInt(result[1], 16),
					g: parseInt(result[2], 16),
					b: parseInt(result[3], 16),
				}
			}
			return null
		},
		rgb2hls(rgb) {
			// RGB2HLS by Garry Tan
			// http://axonflux.com/handy-rgb-to-hsl-and-rgb-to-hsv-color-model-c
			const r = rgb.r / 255
			const g = rgb.g / 255
			const b = rgb.b / 255
			const max = Math.max(r, g, b)
			const min = Math.min(r, g, b)
			let h
			let s
			const l = (max + min) / 2

			if (max === min) {
				h = s = 0 // achromatic
			} else {
				const d = max - min
				s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
				switch (max) {
				case r:
					h = (g - b) / d + (g < b ? 6 : 0)
					break
				case g:
					h = (b - r) / d + 2
					break
				case b:
					h = (r - g) / d + 4
					break
				}
				h /= 6
			}
			return {
				h, l, s,
			}
		},
		textColor(hex) {

			const rgb = this.hexToRgb(hex)
			if (rgb === null) {
				return '#000000'
			}
			const { l } = this.rgb2hls(rgb)

			if (l < 0.5) {
				return '#ffffff'
			} else {
				return '#000000'
			}

		},
		colorIsValid(hex) {

			const re = /[A-Fa-f0-9]{6}/
			if (re.test(hex)) {
				return true
			}
			return false

		},

	},
}
