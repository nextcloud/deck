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
			<h3>{{ card.title }}</h3>
			<action :actions="visibilityPopover" />
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
			menuOpened: false
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
		}
	}
}
</script>

<style lang="scss" scoped>
	.card {
		box-shadow: 0 0 5px #aaa;
		border-radius: 3px;
		margin: 10px;
		width: 260px; // TODO: TMP
		font-size: 100%;
		background-color: var(--color-main-background);

		.card-upper {
			display: flex;
			h3 {
				margin: 12px;
				flex-grow: 1;
				font-size: 100%;
			}
		}

		.labels {
			display: flex;
			margin-left: 12px;
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
			margin-left: 12px;
			margin-right: 12px;
			& > div {
				display: flex;
				height: 44px;
			}
		}
	}

	.compact {
		padding-bottom: 12px;
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
