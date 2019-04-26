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
	<div class="color-picker">
		<div class="color-picker-compact">
			<Compact :palette="defaultColors" @input="updateColor" v-model="color"></Compact>
			<div class="custom-color-button icon-colorpicker" :style="{'background-color': color.hex}" @click="showFullPicker=!showFullPicker"></div>
		</div>
		<Chrome v-if="showFullPicker" :palette="defaultColors" @input="updateColor" v-model="color"></Chrome>
	</div>
</template>

<script>
	// TODO: import styles manually if possible
	import { Compact, Chrome } from 'vue-color'

	export default {
		name: 'ColorPicker',
		components: {
			Compact,
			Chrome
		},
		props: ['value'],
		data() {
			return {
				color: { hex: this.value },
				defaultColors: ['#31CC7C', '#317CCC', '#FF7A66', '#F1DB50', '#7C31CC', '#CC317C', '#3A3B3D', '#CACBCD'],
				showFullPicker: false,
			}
		},
		methods: {
			updateColor() {
				this.$emit('input', this.color)
			}
		}
	}
</script>

<style scoped>
	div.color-picker {
		display: block !important;
	}
	.color-picker-compact {
		display: flex;
	}
	.custom-color-button {
		width: 24px;
		height: 24px;
		display: block;
	}
	.vc-chrome {
		display: block !important;
	}

	.vc-compact {
		padding: 0;
		border-radius: 0;
		box-shadow: none;
		background-color: transparent;
		width: auto;
	}
</style>
<style lang="scss">
	.color-picker {
		.vc-chrome-fields-wrap {
			display: none !important;
		}

		.vc-compact-colors {
			display: flex;
		}
		.vc-compact-color-item {
			display: inline-flex;
			height: 24px;
			width: 24px;
			padding: 0;
			margin: 0;
		}
	}
</style>
