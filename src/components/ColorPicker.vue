<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-click-outside="hidePicker" class="color-picker">
		<div class="color-picker-compact">
			<Compact :palette="defaultColors" v-model="color" @input="updateColor" />
			<div :style="{'background-color': color.hex}" class="custom-color-button icon-colorpicker" @click.prevent="showFullPicker=!showFullPicker" />
		</div>
		<Chrome v-if="showFullPicker" :palette="defaultColors" v-model="color"
			@input="updateColor" />
	</div>
</template>

<script>
// TODO: import styles manually if possible
import { Compact, Chrome } from 'vue-color'
import ClickOutside from 'vue-click-outside'

export default {
	name: 'ColorPicker',
	components: {
		Compact,
		Chrome
	},
	directives: {
		ClickOutside
	},
	props: {
		value: {
			type: [String, Object],
			default: null
		}
	},
	data() {
		return {
			color: { hex: this.value },
			defaultColors: ['#31CC7C', '#317CCC', '#FF7A66', '#F1DB50', '#7C31CC', '#CC317C', '#3A3B3D', '#CACBCD'],
			showFullPicker: false
		}
	},
	methods: {
		updateColor() {
			this.$emit('input', this.color)
		},
		hidePicker() {
			this.showFullPicker = false
		}
	}
}
</script>

<style scoped>
	div.color-picker {
		display: block !important;
		overflow: hidden;
		border-radius: 3px;
		margin-bottom: 10px;
	}
</style>
<style lang="scss">
	$color-field-width: 27px;
	#content .color-picker {
		// this is required to overwrite
		// #app-navigation .app-navigation-entry-edit form, #app-navigation .app-navigation-entry-edit div
		// which has a to wide scope in the server styles
		div {
			width: auto;
			display: block;
		}
		.color-picker-compact {
			display: flex;
		}
		.custom-color-button {
			width: $color-field-width;
			height: $color-field-width;
			display: block;
			flex-grow: 1;
		}

		.vc-compact {
			padding: 0;
			border-radius: 0;
			box-shadow: none;
			background-color: transparent;
			width: 90%
		}

		.vc-chrome {
			border-radius: 0 3px;
			box-shadow: 0 0 2px var(--color-box-shadow);
		}
		.vc-chrome-fields-wrap {
			display: none !important;
		}

		.vc-compact-colors {
			display: flex;
		}
		.vc-compact-color-item {
			display: inline-flex;
			height: $color-field-width;
			padding: 0;
			margin: 0;
			flex-grow: 1;
		}
		.vc-compact-dot {
			width: 10px;
			height: 10px;
			position: unset;
			border-radius: 50%;
			opacity: 1;
			background: #fff;
			margin: auto;
		}
		.vc-chrome-controls {
			display: flex;
		}
		.vc-chrome-active-color {
			position: relative;
			width: 30px;
			height: 30px;
			border-radius: 15px;
			overflow: hidden;
			z-index: 1;
		}
		.vc-chrome-color-wrap {
			position: relative;
			width: 36px;
		}
		.vc-saturation-pointer {
			cursor: pointer;
			position: absolute;
			width: 12px;
			height: 10px;
		}
		.vc-chrome-alpha-wrap {
			display: none;
		}
		.vc-chrome-hue-wrap {
			position: relative;
			height: 10px;
			margin-bottom: 8px;
			margin-top: 10px;
		}
	}
</style>
