<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="deck-comment-reference">
		<div class="line">
			<CardBulletedOutlineIcon :size="20" class="title-icon" />
			<strong>
				<a :href="cardLink"
					:title="cardTooltip"
					target="_blank"
					class="link">
					{{ card.title }}
				</a>
			</strong>
			<div v-if="dueDate" class="spacer" />
			<span v-if="dueDate"
				:title="t('Due date') + ': ' + formattedDueDate"
				class="due-date">
				<CalendarBlankIcon :size="20"
					class="icon" />
				{{ dueDate }}
			</span>
		</div>
		<div class="line">
			<DeckIcon :size="20" class="title-icon" />
			<a :title="stackTooltip"
				:href="boardLink"
				target="_blank"
				class="link">
				{{ t('deck', '{stack} in {board}', { stack: stack.title, board: board.title }) }}
			</a>
		</div>
		<div>
			<transition-group v-if="card.labels && card.labels.length"
				name="zoom"
				tag="ul"
				class="labels"
				@click.stop="openCard">
				<li v-for="label in labelsSorted" :key="label.id" :style="labelStyle(label)">
					<span>{{ label.title }}</span>
				</li>
			</transition-group>
		</div>
		<div class="line description-assignees">
			<TextIcon v-if="card.description" :size="20" class="icon" />
			<div v-if="card.description"
				:class="{
					'description': true,
					'short-description': shortDescription,
				}">
				<NcRichText :title="shortDescription ? t('deck', 'Click to expand description') : undefined"
					:text="card.description"
					:use-markdown="true"
					@click.native="shortDescription = !shortDescription" />
			</div>
			<div v-if="card.assignedUsers .length > 0"
				class="spacer" />
			<AvatarList v-if="card.assignedUsers .length > 0"
				:users="card.assignedUsers"
				class="card-assignees" />
		</div>
		<div v-if="comment" class="line comment-wrapper">
			<CommentProcessingOutlineIcon :size="20" class="icon" />
			<div :class="{
				'comment': true,
				'short-comment': shortComment,
			}">
				<NcRichText :title="shortComment ? t('deck', 'Click to expand comment') : undefined"
					:text="commentMessageText"
					:use-markdown="false"
					@click.native="shortComment = !shortComment" />
			</div>
		</div>
	</div>
</template>

<script>
import CalendarBlankIcon from 'vue-material-design-icons/CalendarBlankOutline.vue'
import TextIcon from 'vue-material-design-icons/Text.vue'
import CardBulletedOutlineIcon from 'vue-material-design-icons/CardBulletedOutline.vue'
import CommentProcessingOutlineIcon from 'vue-material-design-icons/CommentProcessingOutline.vue'

import DeckIcon from '../components/icons/DeckIcon.vue'
import AvatarList from '../components/cards/AvatarList.vue'
import labelStyle from '../mixins/labelStyle.js'

import { NcRichText } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CommentReferenceWidget',

	components: {
	  AvatarList,
		DeckIcon,
		CalendarBlankIcon,
		TextIcon,
		CardBulletedOutlineIcon,
		NcRichText,
		CommentProcessingOutlineIcon,
	},

	mixins: [labelStyle],

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			shortDescription: true,
			shortComment: true,
		}
	},

	computed: {
		card() {
			return this.richObject.card
		},
		board() {
			return this.richObject.board
		},
		stack() {
			return this.richObject.stack
		},
		comment() {
			return this.richObject.comment
		},
		commentMessageText() {
			const e = document.createElement('div')
			e.innerHTML = this.comment.message
			return e.textContent
		},
		cardLink() {
			return generateUrl('/apps/deck/#/board/{boardId}/card/{cardId}', { boardId: this.board.id, cardId: this.card.id })
		},
		boardLink() {
			return generateUrl('/apps/deck/#/board/{boardId}', { boardId: this.board.id })
		},
		cardTooltip() {
			return t('deck', '* Created on {created}\n* Last modified on {lastMod}\n* {nbAttachments} attachments\n* {nbComments} comments', {
			  created: moment.unix(this.card.createdAt).format('LLL'),
				lastMod: moment.unix(this.card.lastModified).format('LLL'),
				nbAttachments: this.card.attachments.length,
				nbComments: this.card.commentsCount,
			})
		},
		stackTooltip() {
			return t('deck', '{nbCards} cards', { nbCards: this.stack.cards.length })
		},
		dueDate() {
			return this.card.duedate
				? moment(this.card.duedate).fromNow()
				: null
		},
		formattedDueDate() {
			return this.card.duedate
				? t('deck', 'Due on {date}', { date: moment(this.card.duedate).format('LLL') })
				: null
		},
		labelsSorted() {
			return [...this.card.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
/* stylelint-disable-next-line no-invalid-position-at-import-rule */
@import '../css/labels';

.deck-comment-reference {
	width: 100%;
	// needed for the specific case of Text
	.editor__content & {
		width: calc(100% - 24px);
	}
	white-space: normal;
	padding: 12px;

	.link {
		text-decoration: underline;
		color: var(--color-main-text) !important;
		padding: 0 !important;
	}

	.line {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
		.title-icon {
			margin-right: 8px;
		}
	}

	.due-date {
		display: flex;
		align-items: center;
	}

	.labels {
		margin: 8px 0;
	}

	.comment-wrapper,
	.description-assignees {
		width: 100%;
		display: flex;
		align-items: start;

		.icon {
			align-self: start;
			margin-top: 8px;
		}

		.comment,
		.description {
			margin-right: 8px;
			padding-top: 6px;
			max-height: 250px;
			overflow: scroll;
			&.short-comment,
			&.short-description {
				max-height: 25px;
				overflow: hidden;
			}
		}

		.card-assignees {
			margin-top: 0;
			height: 36px;
			flex-grow: unset;
		}
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
