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
	<div :class="{'compact': compactMode}" tag="div" class="card"
		@click.self="openCard">
		<div class="card-upper">
			<h3 @click.stop="startEditing(card)">{{ card.title }}</h3>
			<transition name="fade" mode="out-in">
				<form v-if="editing">
					<input v-model="copiedCard.title" type="text" autofocus>
					<input type="button" class="icon-confirm" @click="finishedEdit(card)">
				</form>

				<Actions @click.stop.prevent>
					<ActionButton icon="icon-user" @click="assignCardToMe()">{{ t('deck', 'Assign to me') }}</ActionButton>
					<ActionButton icon="icon-archive" @click="archiveUnarchiveCard()">{{ t('deck', (showArchived ? 'Unarchive card' : 'Archive card')) }}</ActionButton>
					<ActionButton icon="icon-delete" @click="deleteCard()">{{ t('deck', 'Delete card') }}</ActionButton>
					<ActionButton icon="icon-settings-dark" @click="openCard">{{ t('deck', 'Card details') }}</ActionButton>
				</Actions>

			</transition>
		</div>
		<ul class="labels" @click="openCard">
			<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)"><span>{{ label.title }}</span></li>
		</ul>
		<div v-show="!compactMode" class="card-controls compact-item" @click="openCard">
			<card-badges :id="id" />
		</div>
	</div>
</template>

<script>
import { PopoverMenu } from 'nextcloud-vue'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
import ClickOutside from 'vue-click-outside'
import { mapState } from 'vuex'

import CardBadges from './CardBadges'
import LabelTag from './LabelTag'
import Color from '../../mixins/color'

export default {
	name: 'CardItem',
	components: { PopoverMenu, CardBadges, LabelTag, Actions, ActionButton },
	directives: {
		ClickOutside
	},
	mixins: [Color],
	props: {
		id: {
			type: Number,
			default: null
		}
	},
	data() {
		return {
			menuOpened: false,
			editing: false,
			copiedCard: ''
		}
	},
	computed: {
		...mapState({
			compactMode: state => state.compactMode,
			showArchived: state => state.showArchived
		}),
		card() {
			return this.$store.getters.cardById(this.id)
		},
		menu() {
			return []
		},
		labelStyle() {
			return (label) => {
				return {
					backgroundColor: '#' + label.color,
					color: this.textColor(label.color)
				}
			}
		}
	},
	methods: {
		openCard() {
			this.$router.push({ name: 'card', params: { cardId: this.id } })
		},
		togglePopoverMenu() {
			this.menuOpened = !this.menuOpened
		},
		hidePopoverMenu() {
			this.menuOpened = false
		},
		startEditing(card) {
			this.copiedCard = Object.assign({}, card)
			this.editing = true
		},
		finishedEdit(card) {
			if (this.copiedCard.title !== card.title) {
				this.$store.dispatch('updateCard', this.copiedCard)
			}
			this.editing = false
		},
		deleteCard() {
			this.$store.dispatch('deleteCard', this.card)
		},
		archiveUnarchiveCard() {
			this.copiedCard = Object.assign({}, this.card)
			this.copiedCard.archived = !this.copiedCard.archived
			this.$store.dispatch('archiveUnarchiveCard', this.copiedCard)
		},
		assignCardToMe() {
			this.copiedCard = Object.assign({}, this.card)
			this.copiedCard.newUserUid = this.card.owner.uid
			this.$store.dispatch('assignCardToUser', this.copiedCard)
		}
	}
}
</script>

<style lang="scss" scoped>
	$card-spacing: 20px;
	$card-padding: 15px;

	.fade-enter-active, .fade-leave-active {
		transition: opacity .125s;
	}
	.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
		opacity: 0;
	}

	.card {
		box-shadow: 0 0 3px 0 var(--color-box-shadow);
		border-radius: 3px;
		font-size: 100%;
		background-color: var(--color-main-background);
		margin-bottom: $card-spacing;
		&.current {
			box-shadow: 0 0 3px 1px var(--color-box-shadow);
		}

		.card-upper {
			display: flex;
			form {
				display: flex;
				padding: 5px 7px;
				position: absolute;
				width: calc(100% - 14px);
				input[type=text] {
					flex-grow: 1;
				}

			}
			h3 {
				margin: $card-padding;
				flex-grow: 1;
				font-size: 100%;
				cursor: text;
			}
		}

		.labels {
			display: flex;
			margin-left: $card-padding;
			margin-top: -5px;
			margin-bottom: -5px;

			li {
				padding: 1px 4px;
				border-radius: 3px;
				font-size: 90%;
				margin-right: 2px;
			}
		}

		.card-controls {
			display: flex;
			margin-left: $card-padding;
			margin-right: $card-padding;
			& > div {
				display: flex;
				height: 44px;
			}
		}
	}

	.compact {
		padding-bottom: $card-padding;
		.labels {
			height: 6px;
			margin-top: -10px;
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
