<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<router-link :id="`board-${board.id}`"
		:title="board.title"
		:to="routeTo"
		class="board-list-row"
		tag="a">
		<div class="board-list-bullet-cell">
			<div :style="{ 'background-color': `#${board.color}` }" class="board-list-bullet" />
		</div>
		<div class="board-list-title-cell">
			{{ board.title }}
		</div>
		<div class="board-list-avatars-cell" title="">
			<NcAvatar :user="board.owner.uid" :display-name="board.owner.displayname" class="board-list-avatar" />
			<NcAvatar v-for="user in limitedAcl"
				:key="user.id"
				:user="user.participant.uid"
				:display-name="user.participant.displayname"
				class="board-list-avatar" />
			<div v-if="board.acl.length > 5" :title="otherAcl" class="avatardiv popovermenu-wrapper board-list-avatar icon-more" />
		</div>
		<div class="board-list-actions-cell" />
	</router-link>
</template>

<script>
import { NcAvatar } from '@nextcloud/vue'

export default {
	name: 'BoardItem',
	components: {
		NcAvatar,
	},
	props: {
		board: {
			type: Object,
			default: () => { return {} },
		},
	},
	computed: {
		routeTo() {
			return {
				name: 'board',
				params: { id: this.board.id },
			}
		},
		limitedAcl() {
			return [...this.board.acl].splice(0, 5)
		},
		otherAcl() {
			return [...this.board.acl].splice(6).map((item) => item.participant.displayname || item.participant).join(', ')
		},
	},
}
</script>

<style lang="scss" scoped>

	.board-list-row {
		&:hover, &:focus {
			background-color: var(--color-background-hover);
		}
		cursor: pointer;
	}

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
			margin-left: 3px;
			&.icon-more {
				background-color:var(--color-background-dark);
			}
		}
	}

</style>
