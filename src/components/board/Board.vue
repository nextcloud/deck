<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
	<div>
		<Controls :board="board" />
		<div v-if="board">
			board {{ board.title }}<br>
			<button @click="toggleSidebar">toggle sidebar</button>
		</div>
	</div>
</template>

<script>

import { mapState } from 'vuex'
import Controls from '../Controls'

export default {
	name: 'Board',
	components: {
		Controls
	},
	inject: [
		'boardApi'
	],
	props: {
		id: {
			type: Number,
			default: null
		}
	},
	data: function() {
		return {
			loading: true
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard
		})
	},
	created: function() {
		this.boardApi.loadById(this.id)
			.then((board) => {
				this.$store.dispatch('setCurrentBoard', board)
				this.loading = false
			})
	},
	methods: {
		toggleSidebar: function() {
			this.$store.dispatch('toggleSidebar')
		}
	}
}
</script>

<style scoped>

</style>
