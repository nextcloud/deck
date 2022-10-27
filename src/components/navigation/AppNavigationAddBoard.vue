<!--
  - @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<NcAppNavigationItem v-if="!editing"
		:title="t('deck', 'Add board')"
		icon="icon-add"
		@click.prevent.stop="startCreateBoard" />
	<div v-else class="board-create">
		<NcColorPicker v-model="color" class="app-navigation-entry-bullet-wrapper">
			<div :style="{ backgroundColor: color }" class="color0 icon-colorpicker app-navigation-entry-bullet" />
		</NcColorPicker>
		<form @submit.prevent.stop="createBoard">
			<input :placeholder="t('deck', 'Board name')" type="text" required>
			<input type="submit" value="" class="icon-confirm">
			<NcActions><NcActionButton icon="icon-close" @click.stop.prevent="cancelEdit" /></NcActions>
		</form>
	</div>
</template>

<script>
import { NcColorPicker, NcActionButton, NcActions, NcAppNavigationItem } from '@nextcloud/vue'

/**
 *
 */
function randomColor() {
	let randomHexColor = ((1 << 24) * Math.random() | 0).toString(16)
	while (randomHexColor.length < 6) {
		randomHexColor = '0' + randomHexColor
	}
	return '#' + randomHexColor
}

export default {
	name: 'AppNavigationAddBoard',
	components: { NcColorPicker, NcAppNavigationItem, NcActionButton, NcActions },
	directives: {},
	props: {},
	data() {
		return {
			classes: [],
			editing: false,
			loading: false,
			color: randomColor(),
		}
	},
	computed: {},
	watch: {},
	mounted() {},
	methods: {
		startCreateBoard(e) {
			this.editing = true
		},
		createBoard(e) {
			const title = e.currentTarget.childNodes[0].value
			this.$store.dispatch('createBoard', {
				title,
				color: this.color.substring(1),
			})
			this.editing = false
			this.color = randomColor()
		},
		cancelEdit(e) {
			this.editing = false
			this.color = randomColor()
		},
	},
}
</script>
<style lang="scss" scoped>
	.board-create {
		order: 1;
		display: flex;
		height: 44px;

		form {
			display: flex;
			flex-grow: 1;

			input[type='text'] {
				flex-grow: 1;
			}
		}
	}

	.app-navigation-entry-bullet-wrapper {
		width: 44px;
		height: 44px;
		.color0 {
			width: 30px !important;
			margin: 5px;
			margin-left: 7px;
			height: 30px;
			border-radius: 50%;
			background-size: 14px;
		}
	}
</style>
