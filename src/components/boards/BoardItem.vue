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
	<div class="board-list-row">
		<div class="board-list-bullet-cell">
			<div :style="{ 'background-color': board.bullet }" class="board-list-bullet" />
		</div>
		<div class="board-list-title-cell">{{ board.text }}</div>
		<div class="board-list-avatars-cell">
			<img :src="`/avatar/${board.owner.uid}/32`" class="board-list-avatar">
		</div>
		<div class="board-list-actions-cell" />
	</div>
</template>

<script>
export default {
	name: 'BoardItem',
	props: {
		board: {
			type: Object,
			default: () => { return {} }
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
		padding: 6px 15px;

		.board-list-avatar {
			border-radius: 50%;
			height: 32px;
			width: 32px;
		}
	}

</style>
