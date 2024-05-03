<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigationItem v-if="!editing"
		:name="t('deck', 'Add board')"
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
		async createBoard(e) {
			const title = e.currentTarget.childNodes[0].value
			await this.$store.dispatch('createBoard', {
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
