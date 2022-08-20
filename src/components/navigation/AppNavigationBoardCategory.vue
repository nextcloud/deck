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
	<NcAppNavigationItem v-if="boards.length > 0"
		:title="text"
		:to="to"
		:exact="true"
		:allow-collapse="collapsible"
		:open="opened">
		<AppNavigationBoard v-for="board in boardsSorted" :key="board.id" :board="board" />
		<template #icon>
			<slot name="icon" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import AppNavigationBoard from './AppNavigationBoard'
import { NcAppNavigationItem } from '@nextcloud/vue'

export default {
	name: 'AppNavigationBoardCategory',
	components: {
		NcAppNavigationItem,
		AppNavigationBoard,
	},
	props: {
		to: {
			type: String,
			default: '',
		},
		id: {
			type: String,
			required: true,
		},
		text: {
			type: String,
			required: true,
		},
		boards: {
			type: Array,
			required: true,
		},
		/**
		 * Control whether the category should be opened when adding boards.
		 * This is for example used in the case a new board has been added, so the user directly sees it.
		 */
		openOnAddBoards: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			opened: false,
		}
	},
	computed: {
		boardsSorted() {
			return [...this.boards].sort((a, b) => a.title.localeCompare(b.title))
		},
		collapsible() {
			return this.boards.length > 0
		},
	},
	watch: {
		boards(newVal, prevVal) {
			if (this.openOnAddBoards === true && prevVal.length < newVal.length) {
				this.opened = true
			}
		},
	},
}
</script>
