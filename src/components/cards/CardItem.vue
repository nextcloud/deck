<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @author Gary Kim <gary@garykim.dev>
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
	<AttachmentDragAndDrop v-if="card" :card-id="card.id" class="drop-upload--card">
		<div :ref="`card${card.id}`"
			:class="{'compact': compactMode, 'current-card': currentCard, 'has-labels': card.labels && card.labels.length > 0, 'card__editable': canEdit, 'card__archived': card.archived }"
			tag="div"
			:tabindex="0"
			class="card"
			@click="openCard"
			@keyup.self="handleCardKeyboardShortcut"
			@mouseenter="focus(card.id)">
			<div v-if="standalone" class="card-related">
				<div :style="{backgroundColor: '#' + board.color}" class="board-bullet" dir="auto" />
				{{ board.title }} » {{ stack.title }}
			</div>
			<CardCover v-if="showCardCover" :card-id="card.id" />
			<div class="card-upper">
				<h3 v-if="inlineEditingBlocked" dir="auto">
					{{ card.title }}
				</h3>
				<h3 v-else
					dir="auto"
					class="editable"
					:aria-label="t('deck', 'Edit card title')">
					<span ref="titleContentEditable"
						tabindex="0"
						contenteditable="true"
						role="textbox"
						@focus="onTitleFocus"
						@blur="onTitleBlur"
						@click.stop
						@keyup.esc="cancelEdit"
						@keyup.stop>{{ card.title }}</span>
				</h3>

				<DueDate v-if="compactMode && card.duedate" :card="card" />
				<CardMenu v-if="showMenuAtTitle" :card="card" class="right card-menu" />
			</div>

			<div v-if="hasLabels" class="card-labels">
				<transition-group v-if="card.labels && card.labels.length"
					name="zoom"
					tag="ul"
					class="labels"
					@click.stop="openCard">
					<li v-for="label in labelsSorted" :key="label.id" :style="labelStyle(label)">
						<span @click.stop="applyLabelFilter(label)">{{ label.title }}</span>
					</li>
				</transition-group>
				<CardMenu v-if="showMenuAtLabels" :card="card" class="right" />
			</div>

			<div v-if="hasBadges"
				v-show="!compactMode"
				class="card-controls compact-item"
				@click="openCard">
				<CardBadges :card="card">
					<CardMenu v-if="showMenuAtBadges" :card="card" class="right" />
				</CardBadges>
			</div>
		</div>
	</AttachmentDragAndDrop>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import { mapState, mapGetters } from 'vuex'
import CardBadges from './CardBadges.vue'
import Color from '../../mixins/color.js'
import labelStyle from '../../mixins/labelStyle.js'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop.vue'
import CardMenu from './CardMenu.vue'
import CardCover from './CardCover.vue'
import DueDate from './badges/DueDate.vue'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'CardItem',
	components: { CardBadges, AttachmentDragAndDrop, CardMenu, CardCover, DueDate },
	directives: {
		ClickOutside,
	},
	mixins: [Color, labelStyle],
	props: {
		id: {
			type: Number,
			default: null,
		},
		item: {
			type: Object,
			default: null,
		},
		standalone: {
			type: Boolean,
			default: false,
		},
		dragging: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapState({
			compactMode: state => state.compactMode,
			showArchived: state => state.showArchived,
			currentBoard: state => state.currentBoard,
			showCardCover: state => state.showCardCover,
		}),
		...mapGetters([
			'isArchived',
		]),
		board() {
			return this.$store.getters.boardById(this?.stack?.boardId)
		},
		stack() {
			return this.$store.getters.stackById(this?.card?.stackId)
		},
		canEdit() {
			if (this.currentBoard) {
				return !this.currentBoard.archived && this.$store.getters.canEdit
			}
			const board = this.$store.getters.boards.find((item) => item.id === this.card.boardId)
			return board ? !board.archived && board.permissions.PERMISSION_EDIT : false
		},
		inlineEditingBlocked() {
			return this.isArchived || this.showArchived || !this.canEdit || this.standalone
		},
		card() {
			return this.item ? this.item : this.$store.getters.cardById(this.id)
		},
		currentCard() {
			return this.card && this.$route && this.$route.params.cardId === this.card.id
		},
		labelsSorted() {
			return [...this.card.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
		hasLabels() {
			return this.card.labels.length > 0
		},
		hasBadges() {
			return this.card.done
				|| this.card.duedate
				|| this.idBadge
				|| this.card.commentsCount > 0
				|| this.card.description
				|| this.card.attachmentCount > 0
				|| this.card.assignedUsers.length > 0
		},
		idBadge() {
			return this.$store.getters.config('cardIdBadge')
		},
		showMenuAtTitle() {
			return this.compactMode || (!this.compactMode && !this.hasBadges && !this.hasLabels)
		},
		showMenuAtLabels() {
			if (this.compactMode) {
				return false
			}
			return !this.hasBadges && this.hasLabels
		},
		showMenuAtBadges() {
			if (this.compactMode) {
				return false
			}
			return this.hasBadges
		},
	},
	watch: {
		currentCard(newValue) {
			if (newValue) {
				this.$nextTick(() => this.$el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' }))
			}
		},
		'card.title'(value) {
			if (document.activeElement === this.$refs.titleContentEditable || this.$refs.titleContentEditable.textContent === value) {
				return
			}
			this.$refs.titleContentEditable.textContent = value
		},
	},
	methods: {
		focus(card) {
			card = this.$refs[`card${card}`]
			card.focus()
		},
		openCard() {
			if (this.dragging) {
			  return
			}
			const boardId = this.card && this.card.boardId ? this.card.boardId : this.$route.params.id
			this.$router.push({ name: 'card', params: { id: boardId, cardId: this.card.id } }).catch(() => {})
		},
		onTitleBlur(e) {
			// TODO Handle empty title
			if (e.target.innerText !== this.card.title) {
				this.$store.dispatch('updateCardTitle', {
					...this.card,
					title: e.target.innerText,
				})
			}
			this.$store.dispatch('toggleShortcutLock', false)
		},
		onTitleFocus() {
			this.$store.dispatch('toggleShortcutLock', true)
		},
		cancelEdit() {
			this.$refs.titleContentEditable.textContent = this.card.title
			this.$store.dispatch('toggleShortcutLock', false)
		},
		handleCardKeyboardShortcut(key) {
			if (OCP.Accessibility.disableKeyboardShortcuts()) {
				return
			}

			if (!this.canEdit || this.$store.state.shortcutLock || key.shiftKey || key.ctrlKey || key.altKey || key.metaKey) {
				return
			}

			switch (key.code) {
			case 'KeyE':
				this.$refs.titleContentEditable?.focus()
				break
			case 'KeyA':
				this.$store.dispatch('archiveUnarchiveCard', { ...this.card, archived: !this.card.archived })
				break
			case 'KeyO':
				this.$store.dispatch('changeCardDoneStatus', { ...this.card, done: !this.card.done })
				break
			case 'KeyM':
				this.$el.querySelector('button.action-item__menutoggle')?.click()
				break
			case 'Enter':
			case 'Space':
				this.openCard().then(() => document.getElementById('app-sidebar-vue')?.focus())
				break
			case 'KeyS':
				this.toggleSelfAsignment()
				break
			}
		},
		applyLabelFilter(label) {
			if (this.dragging) {
				return
			}
			this.$nextTick(() => this.$store.dispatch('toggleFilter', { tags: [label.id] }))
		},
		toggleSelfAsignment() {
			const isAssigned = this.card.assignedUsers.find(
				(item) => item.type === 0 && item.participant.uid === getCurrentUser()?.uid,
			)
			this.$store.dispatch(isAssigned ? 'removeUserFromCard' : 'assignCardToUser', {
				card: this.card,
				assignee: {
					userId: getCurrentUser()?.uid,
					type: 0,
				},
			})
		},
	},
}
</script>

<style lang="scss" scoped>
	@import './../../css/animations';
	@import './../../css/variables';

	@mixin dark-card {
		border: 2px solid var(--color-border-dark);
		box-shadow: none;
	}

	.card {
		transition: border 0.1s ease-in-out;
		border-radius: var(--border-radius-large);
		font-size: 100%;
		background-color: var(--color-main-background);
		margin-bottom: $card-spacing;
		padding: var(--default-grid-baseline) $card-padding;
		border: 2px solid var(--color-border);
		width: 100%;
		display: flex;
		flex-direction: column;
		gap: 6px;

		&:deep(*) {
			cursor: pointer;
		}

		&.current-card {
			border: 2px solid var(--color-primary-element);
		}

		&:focus, &:focus-visible, &:focus-within {
			outline: none;
			border: 2px solid var(--color-border-maxcontrast);
			&.current-card {
				border: 2px solid var(--color-primary-element);
			}
		}

		.card-upper {
			display: flex;
			h3 {
				margin: 0;
				padding: 6px;
				flex-grow: 1;
				font-size: 100%;
				overflow: hidden;
				word-wrap: break-word;
				padding-left: 4px;
				align-self: center;
				&.editable {
					span {
						cursor: text;
						padding-right: 8px;
						padding-top: 3px;
						padding-bottom: 3px;

						&:focus, &:focus-visible {
							outline: none;
						}
					}

					&:focus-within {
						outline: 2px solid var(--color-border-dark);
						border-radius: 3px;
					}
				}
			}
			.card-menu {
				height: 44px;
				align-self: end;
			}
		}

		/* stylelint-disable-next-line no-invalid-position-at-import-rule */
		@import './../../css/labels';

		.card-controls {
			display: flex;
		}
		&.card__editable .card-controls {
			margin-right: 0;
		}
		&.card__archived {
			background-color: var(--color-background-dark);
		}
		.card-labels {
			display: flex;
			align-items: end;
			margin-bottom: var(--default-grid-baseline);

			.labels {
				flex-wrap: wrap;
				align-self: flex-start;
			}
		}
	}

	.right {
		display: flex;
		align-items: flex-start;
	}

	.card-related {
		display: flex;
		padding: 12px;
		padding-bottom: 0px;
		color: var(--color-text-maxcontrast);

		.board-bullet {
			display: inline-block;
			width: 12px;
			height: 12px;
			border: none;
			border-radius: 50%;
			background-color: transparent;
			margin-top: 4px;
			margin-right: 4px;
		}
	}

	.compact {
		min-height: 44px;

		.duedate {
			margin-right: 0;
			display: flex;
			height: 32px;
			width: 32px;
			margin-top: 6px;
		}
		&.has-labels {
			padding-bottom: $card-padding;
		}
		.labels {
			height: 6px;
			margin-top: -7px;
			margin-bottom: 3px;
		}
		.labels li {
			width: 30px;
			height: 6px;
			font-size: 0;
			color: transparent;
		}
		.card-menu {
			align-self: start !important;
		}
	}

	/* Add horizontal scrollbar for tables in card descriptions */
	.description-container table {
    	width: 100%;
    	overflow-x: auto;
  	}

	@media (prefers-color-scheme: dark) {
		.card {
			@include dark-card;
		}
	}

	@media print {
		.card-menu {
			display: none;
		}
	}
</style>
