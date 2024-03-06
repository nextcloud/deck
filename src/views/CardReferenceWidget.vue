<!--
  - @copyright Copyright (c) 2022 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author 2022 Julien Veyssier <julien-nc@posteo.net>
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
  -->

<template>
	<div class="deck-card-reference">
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
	</div>
</template>

<script>
import CalendarBlankIcon from 'vue-material-design-icons/CalendarBlank.vue'
import TextIcon from 'vue-material-design-icons/Text.vue'
import CardBulletedOutlineIcon from 'vue-material-design-icons/CardBulletedOutline.vue'

import DeckIcon from '../components/icons/DeckIcon.vue'
import AvatarList from '../components/cards/AvatarList.vue'
import labelStyle from '../mixins/labelStyle.js'

import { NcRichText } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'CardReferenceWidget',

	components: {
	  AvatarList,
		DeckIcon,
		CalendarBlankIcon,
		CardBulletedOutlineIcon,
		TextIcon,
		NcRichText,
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

.deck-card-reference {
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

	.description-assignees {
		width: 100%;
		display: flex;
		align-items: start;

		.icon {
			align-self: start;
			margin-top: 8px;
		}

		.description {
			margin-right: 8px;
			padding-top: 6px;
			max-height: 250px;
			overflow: scroll;
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
