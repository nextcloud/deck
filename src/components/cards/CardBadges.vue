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
	<div v-if="card" class="badges">
		<div v-if="card.commentsCount > 0"
			v-tooltip="commentsHint"
			class="icon-badge"
			@click.stop="openComments">
			<CommentUnreadIcon v-if="card.commentsUnread > 0" :size="16" />
			<CommentIcon v-else :size="16" />
			<span>{{ card.commentsCount }}</span>
		</div>

		<div v-if="card.description && checkListCount > 0" class="icon-badge">
			<CheckmarkIcon :size="16" :title="t('deck', 'Todo items')" />
			<span>{{ checkListCheckedCount }}/{{ checkListCount }}</span>
		</div>

		<TextIcon v-else-if="card.description.trim() && checkListCount == 0" :size="16" decorative />

		<div v-if="card.attachmentCount > 0" class="icon-badge">
			<AttachmentIcon :size="16" />
			<span>{{ card.attachmentCount }}</span>
		</div>

		<NcAvatarList :users="card.assignedUsers" />

		<CardMenu class="card-menu" :card="card" />
	</div>
</template>
<script>
import NcAvatarList from './AvatarList'
import CardMenu from './CardMenu'
import TextIcon from 'vue-material-design-icons/Text.vue'
import AttachmentIcon from 'vue-material-design-icons/Paperclip.vue'
import CheckmarkIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CommentIcon from 'vue-material-design-icons/Comment.vue'
import CommentUnreadIcon from 'vue-material-design-icons/CommentAccount.vue'

export default {
	name: 'CardBadges',
	components: { NcAvatarList, CardMenu, TextIcon, AttachmentIcon, CheckmarkIcon, CommentIcon, CommentUnreadIcon },
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	computed: {
		checkListCount() {
			return (this.card.description.match(/^\s*([*+-]|(\d\.))\s+\[\s*(\s|x)\s*\](.*)$/gim) || []).length
		},
		checkListCheckedCount() {
			return (this.card.description.match(/^\s*([*+-]|(\d\.))\s+\[\s*x\s*\](.*)$/gim) || []).length
		},
		commentsHint() {
			if (this.card.commentsUnread > 0) {
				return t('deck', '{count} comments, {unread} unread', {
					count: this.card.commentsCount,
					unread: this.card.commentsUnread,
				})
			}
			return null
		},
	},
	methods: {
		openComments() {
			const boardId = this.card && this.card.boardId ? this.card.boardId : this.$route.params.id
			this.$router.push({ name: 'card', params: { id: boardId, cardId: this.card.id, tabId: 'comments' } })
		},
	},
}
</script>

<style lang="scss" scoped>
	.badges {
		display: flex;
		width: 100%;
		flex-grow: 1;

		.icon-badge {
			opacity: .7;
			display: flex;
			margin-right: 2px;

			span {
				padding: 10px 2px;
			}
		}
	}

	.badges .icon.due {
		background-position: 4px center;
		border-radius: 3px;
		margin-top: 10px;
		margin-bottom: 10px;
		padding: 4px;
		font-size: 90%;
		display: flex;
		align-items: center;
		opacity: .5;
		flex-shrink: 1;

		.icon {
			background-size: contain;
		}

		&.overdue {
			background-color: var(--color-error);
			color: var(--color-primary-text);
			opacity: .7;
		}
		&.now {
			background-color: var(--color-warning);
			opacity: .7;
		}
		&.next {
			background-color: var(--color-background-dark);
			opacity: .7;
		}

		span {
			margin-left: 20px;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
		}
	}

	.fade-enter-active, .fade-leave-active {
		transition: opacity .125s;
	}

	.fade-enter, .fade-leave-to {
		opacity: 0;
	}

	@media print {
		.badges {
			align-items: flex-start;
			max-height: none !important;
		}

		.card-menu {
			display: none;
		}
	}
</style>
