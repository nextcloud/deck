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
	<li id="deck-navigation-add"
		:title="t('deck', 'Create new board')" :class="[{'icon-loading-small': loading, 'editing': editing}, classes]">

		<a class="icon-add" href="#" @click.prevent.stop="startCreateBoard">
			{{ t('deck', 'Create new board') }}
		</a>

		<!-- edit entry -->
		<div v-if="editing" class="app-navigation-entry-edit">
			<form @submit.prevent.stop="createBoard">
				<input :placeholder="t('deck', 'New board title')" type="text" required>
				<input type="submit" value="" class="icon-confirm">
				<input type="submit" value="" class="icon-close"
					@click.stop.prevent="cancelEdit">
			</form>
			<ColorPicker v-model="color" />
		</div>
	</li>
</template>

<script>
import ColorPicker from '../ColorPicker'
export default {
	name: 'AppNavigationAddBoard',
	components: { ColorPicker },
	directives: {},
	props: {},
	data() {
		return {
			classes: [],
			editing: false,
			loading: false,
			color: '#000000'
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
				title: title,
				color: this.color.substring(1)
			})
			this.editing = false
		},
		cancelEdit(e) {
			this.editing = false
			this.item.edit.reset(e)
		}
	}
}
</script>
<style scoped>
	#app-navigation .app-navigation-entry-edit div {
		width: auto;
		display: block;
	}
</style>
