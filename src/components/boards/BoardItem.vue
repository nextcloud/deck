<!--
* @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
*
* @author Michael Weimann <mail@michael-weimann.eu>
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
-->

<template>
	<router-link :id="`board-${board.id}`"
		:title="board.title"
		:to="routeTo" class="board-list-row" tag="div">
		<div class="board-list-bullet-cell">
			<div :style="{ 'background-color': board.bullet }" class="board-list-bullet" />
		</div>
		<div class="board-list-title-cell">{{ board.text }}</div>
		<div class="board-list-avatars-cell">
			<avatar :user="board.owner.uid" class="board-list-avatar" />
			<avatar v-for="user in board.board.acl" :key="user.id" :user="user.participant.uid"
				class="board-list-avatar" />
		</div>
		<div class="board-list-actions-cell" />
	</router-link>
</template>

<script>
import { Avatar } from 'nextcloud-vue'

export default {
	name: 'BoardItem',
	components: {
		Avatar
	},
	props: {
		board: {
			type: Object,
			default: () => { return {} }
		}
	},
	computed: {
		routeTo: function() {
			return {
				name: 'board',
				params: { id: this.board.id }
			}
		}
	}
}
</script>

<style lang="scss" scoped>

	.board-list-bullet-cell {
		padding: 6px 15px;

		.board-list-bullet {
			border-radius: 50%;
			cursor: pointer;
			height: 32px;
			width: 32px;
		}
	}

	.board-list-title-cell {
		padding: 0 15px;
	}

	.board-list-avatars-cell {
		display: flex;
		padding: 6px 15px;

		.board-list-avatar {
			border-radius: 50%;
			height: 32px;
			width: 32px;
		}
	}

</style>
