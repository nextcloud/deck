<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="card" class="badges">
		<div class="badge-left">
			<DueDate v-if="card.duedate || card.done" :card="card" />

			<div class="inline-badges">
				<CardId v-if="idBadge" class="icon-badge" :card="card" />

				<div v-if="card.commentsCount > 0"
					:title="commentsHint"
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

				<div v-else-if="card.description && card.description.trim() && checkListCount == 0" class="icon-badge">
					<TextIcon :size="16" decorative />
				</div>

				<div v-if="card.attachmentCount > 0" class="icon-badge">
					<AttachmentIcon :size="16" />
					<span>{{ card.attachmentCount }}</span>
				</div>
			</div>
		</div>

		<div class="badge-right">
			<NcAvatarList :users="card.assignedUsers" :size="32" />

			<slot />
		</div>
	</div>
</template>
<script>
import NcAvatarList from './AvatarList.vue'
import CardId from './badges/CardId.vue'
import TextIcon from 'vue-material-design-icons/Text.vue'
import AttachmentIcon from 'vue-material-design-icons/Paperclip.vue'
import CheckmarkIcon from 'vue-material-design-icons/CheckboxMarked.vue'
import CommentIcon from 'vue-material-design-icons/Comment.vue'
import CommentUnreadIcon from 'vue-material-design-icons/CommentAccount.vue'
import DueDate from './badges/DueDate.vue'

export default {
	name: 'CardBadges',
	components: {
		DueDate,
		NcAvatarList,
		TextIcon,
		AttachmentIcon,
		CheckmarkIcon,
		CommentIcon,
		CommentUnreadIcon,
		CardId,
	},
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
		idBadge() {
			return this.$store.getters.config('cardIdBadge')
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
		flex-direction: row;
		gap: 3px;

		.icon-badge {
			color: var(--color-text-maxcontrast);
			display: flex;
			margin-right: 2px;

			span,
			&:deep(span) {
				padding: 2px;
			}
		}
	}

	.inline-badges {
		display: flex;
		flex-direction: row;
		flex-wrap: wrap;
		gap: 3px;
	}

	.badges .icon.due {
		background-position: 4px center;
		border-radius: var(--border-radius);
		padding: 4px;
		font-size: 13px;
		display: flex;
		align-items: center;
		opacity: .5;
		flex-shrink: 1;

		.icon {
			background-size: contain;
		}

		&.overdue {
			background-color: var(--color-error);
			color: var(--color-primary-element-text);
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

	.badge-left, .badge-right {
		display: flex;
	}

	.badge-left {
		align-self: end;
		margin-bottom: 8px;
		flex-basis: auto;
		flex-grow: 1;
		flex-shrink: 1;
		flex-wrap: wrap;
		align-content: flex-end;
		gap: 3px;
	}

	.badge-right {
		align-items: center;
		align-self: flex-end;
		display: flex;
		justify-items: center;
		max-width: 165px;
		flex-grow: 0;
		flex-shrink: 0;
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
	}
</style>
