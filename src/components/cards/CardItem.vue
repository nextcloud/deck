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
		@click="openCard">
		<div class="card-upper">
			<h3 @click.stop="startEditing">{{ card.title }}</h3>
			<transition name="fade" mode="out-in">
				<form v-if="editing">
					<input :value="card.title" type="text" autofocus>
					<input type="button" class="icon-confirm" @click.stop="editing=false">
				</form>
				<action v-if="!editing" :actions="visibilityPopover" @click.stop="" />
			</transition>
		</div>
		<ul class="labels">
			<li v-for="label in labels" :key="label.id" :style="labelStyle(label)"><span>{{ label.title }}</span></li>
		</ul>
		<div v-show="!compactMode" class="card-controls compact-item">
			<card-badges />
		</div>
	</div>
</template>

<script>
import { PopoverMenu, Action } from 'nextcloud-vue'
import ClickOutside from 'vue-click-outside'
import { mapState } from 'vuex'

import CardBadges from './CardBadges'
import LabelTag from './LabelTag'
import Color from '../../mixins/color'

export default {
	name: 'CardItem',
	components: { PopoverMenu, CardBadges, LabelTag, Action },
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
			editing: false
		}
	},
	computed: {
		...mapState({
			compactMode: state => state.compactMode
		}),
		card() {
			return this.$store.getters.cardById(this.id)
		},
		menu() {
			return []
		},
		labels() {
			return [
				{ id: 1, title: 'ToDo', color: 'aa0000' },
				{ id: 2, title: 'Done', color: '33ff33' }
			]
		},
		labelStyle() {
			return (label) => {
				return {
					backgroundColor: '#' + label.color,
					color: this.textColor(label.color)
				}
			}
		},
		visibilityPopover() {
			return [
				{
					action: () => {},
					icon: 'icon-archive-dark',
					text: t('deck', 'Archive card')
				},
				{
					action: () => {},
					icon: 'icon-settings-dark',
					text: t('deck', 'Card details')
				}
			]
		}
	},
	methods: {
		openCard() {
			this.$router.push({ name: 'card', params: { cardId: 123 } })
		},
		togglePopoverMenu() {
			this.menuOpened = !this.menuOpened
		},
		hidePopoverMenu() {
			this.menuOpened = false
		},
		startEditing() {
			this.editing = true
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
				input[type=text] {
					width: 100%;
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
