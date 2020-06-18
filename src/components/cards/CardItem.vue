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
	<AttachmentDragAndDrop :card-id="id" class="drop-upload--card">
		<div :class="{'compact': compactMode, 'current-card': currentCard, 'has-labels': card.labels && card.labels.length > 0, 'is-editing': editing}"
			tag="div"
			class="card"
			@click="openCard">
			<div class="card-upper">
				<h3 v-if="isArchived || showArchived || !canEdit">
					{{ card.title }}
				</h3>
				<h3 v-else-if="!editing">
					<span @click.stop="startEditing(card)">{{ card.title }}</span>
				</h3>

				<form v-if="editing"
					v-click-outside="cancelEdit"
					class="dragDisabled"
					@keyup.esc="cancelEdit"
					@submit.prevent="finishedEdit(card)">
					<input v-model="copiedCard.title" type="text" autofocus>
					<input type="button" class="icon-confirm" @click="finishedEdit(card)">
				</form>

				<div v-if="!editing" class="duedate right">
					<transition name="zoom">
						<div v-if="card.duedate" :class="dueIcon">
							<span>{{ relativeDate }}</span>
						</div>
					</transition>
				</div>

				<CardMenu v-if="!editing && compactMode" :id="id" class="right" />
			</div>
			<transition-group v-if="card.labels.length"
				name="zoom"
				tag="ul"
				class="labels"
				@click="openCard">
				<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)">
					<span>{{ label.title }}</span>
				</li>
			</transition-group>
			<div v-show="!compactMode" class="card-controls compact-item" @click="openCard">
				<CardBadges :id="id" />
			</div>
		</div>
	</AttachmentDragAndDrop>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import { mapState, mapGetters } from 'vuex'
import moment from 'moment'
import CardBadges from './CardBadges'
import Color from '../../mixins/color'
import labelStyle from '../../mixins/labelStyle'
import AttachmentDragAndDrop from '../AttachmentDragAndDrop'
import CardMenu from './CardMenu'

export default {
	name: 'CardItem',
	components: { CardBadges, AttachmentDragAndDrop, CardMenu },
	directives: {
		ClickOutside,
	},
	mixins: [Color, labelStyle],
	props: {
		id: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			editing: false,
			copiedCard: null,
		}
	},
	computed: {
		...mapState({
			compactMode: state => state.compactMode,
			showArchived: state => state.showArchived,
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters([
			'canEdit',
			'isArchived',
		]),
		card() {
			return this.$store.getters.cardById(this.id)
		},
		currentCard() {
			return this.$route.params.cardId === this.id
		},
		relativeDate() {
			const diff = moment(this.$root.time).diff(this.card.duedate, 'seconds')
			if (diff >= 0 && diff < 45) {
				return t('core', 'seconds ago')
			}
			return moment(this.card.duedate).fromNow()
		},
		dueIcon() {
			const days = Math.floor(moment(this.card.duedate).diff(this.$root.time, 'seconds') / 60 / 60 / 24)
			if (days < 0) {
				return 'icon-calendar due icon overdue'
			}
			if (days === 0) {
				return 'icon-calendar-dark due icon now'
			}
			if (days === 1) {
				return 'icon-calendar-dark due icon next'
			}
			return 'icon-calendar-dark due icon'
		},
		dueDateTooltip() {
			return moment(this.card.duedate).format('LLLL')
		},
	},
	methods: {
		openCard() {
			this.$router.push({ name: 'card', params: { cardId: this.id } }).catch(() => {})
		},
		startEditing(card) {
			this.copiedCard = Object.assign({}, card)
			this.editing = true
		},
		finishedEdit(card) {
			if (this.copiedCard.title !== card.title) {
				this.$store.dispatch('updateCardTitle', this.copiedCard)
			}
			this.editing = false
		},
		cancelEdit() {
			this.editing = false
		},
	},
}
</script>

<style lang="scss" scoped>
	@import './../../css/animations';

	$card-spacing: 10px;
	$card-padding: 10px;

	body.dark .card {
		border: 1px solid var(--color-border);
	}

	.card:hover {
		box-shadow: 0 0 5px 1px var(--color-box-shadow);
	}

	.card {
		transition: box-shadow 0.1s ease-in-out;
		box-shadow: 0 0 2px 0 var(--color-box-shadow);
		border-radius: 3px;
		font-size: 100%;
		background-color: var(--color-main-background);
		margin-bottom: $card-spacing;

		&.current-card {
			box-shadow: 0 0 5px 0 var(--color-box-shadow);
		}

		.card-upper {
			display: flex;
			min-height: 44px;
			form {
				display: flex;
				padding: 5px 7px;
				width: 100%;
				input[type=text] {
					flex-grow: 1;
				}

			}
			h3 {
				margin: 12px $card-padding;
				flex-grow: 1;
				font-size: 100%;
				overflow-x: hidden;
				word-wrap: break-word;
				span {
					cursor: text;
				}
			}
		}

		.labels {
			flex-grow: 1;
			flex-shrink: 1;
			min-width: 0;
			display: flex;
			flex-direction: row;
			margin-left: $card-padding;
			margin-right: $card-padding;
			margin-top: -5px;

			li {
				flex-grow: 0;
				flex-shrink: 1;
				display: flex;
				flex-direction: row;
				overflow: hidden;
				padding: 0px 5px;
				border-radius: 15px;
				font-size: 85%;
				margin-right: 3px;
				margin-bottom: 3px;

				&:hover {
					overflow: unset;
				}

				span {
					flex-grow: 0;
					flex-shrink: 1;
					min-width: 0;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
				}
			}
		}

		.card-controls {
			display: flex;
			margin-left: $card-padding;
			& > div {
				display: flex;
				max-height: 44px;
			}
		}
	}
	.duedate {
		margin-right: 9px;
	}
	.right {
		display: flex;
		align-items: flex-start;
	}

	.icon.due {
		background-position: 4px center;
		border-radius: 3px;
		margin-top: 9px;
		margin-bottom: 9px;
		padding: 3px 4px;
		padding-right: 0;
		font-size: 90%;
		display: flex;
		align-items: center;
		opacity: .5;
		flex-shrink: 1;
		z-index: 2;

		.icon {
			background-size: contain;
		}

		&.overdue {
			background-color: var(--color-error);
			color: var(--color-primary-text);
			opacity: .7;
			padding: 3px 4px;
		}
		&.now {
			background-color: var(--color-warning);
			opacity: .7;
			padding: 3px 4px;
		}
		&.next {
			background-color: var(--color-background-dark);
			opacity: .7;
			padding: 3px 4px;
		}

		span {
			margin-left: 20px;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
		}
	}

	.compact {
		min-height: 44px;

		.duedate {
			margin-right: 0;
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
	}
</style>
