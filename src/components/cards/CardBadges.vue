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
	<div class="badges">
		<div v-if="card.attechments" class="card-files icon icon-files-dark" />
		<div v-if="card.description" class="card-comments icon icon-comment" />

		<!-- <div v-if="card.duedate" :class="{'icon-calendar': true, 'icon-calendar-dark': false}" class="due icon now"> -->
		<div v-if="card.duedate" :class="dueIcon">
			<span>{{ dueTimeDiff }}</span>
		</div>
		<div v-if="card.description && card.description.match(/\[\s*\]/g)" class="card-tasks icon icon-checkmark">
			<span>0/0</span>
		</div>
		<div v-if="card.assignedUsers" class="card-assigned-users">
			<avatar v-for="user in card.assignedUsers" :key="user.id" user="user.participant.primaryKey" />
		</div>
	</div>
</template>
<script>
import { Avatar } from 'nextcloud-vue'

export default {
	name: 'CardBadges',
	components: { Avatar },
	props: {
		id: {
			type: Number,
			default: null
		}
	},
	computed: {
		compactMode() {
			return false
		},
		dueIcon() {
			let timeInHours = Math.round((Date.parse(this.card.duedate) - Date.now()) / 1000 / 60 / 60 / 24)

			if (timeInHours === 1) {
				return 'icon-calendar-dark due icon next'
			}
			if (timeInHours === 0) {
				return 'icon-calendar-dark due icon now'
			}
			if (timeInHours < 0) {
				return 'icon-calendar-dark due icon overdue'
			}
		},
		dueTimeDiff() {
			let unit = 'Minutes'
			let timeInMin = (Date.parse(this.card.duedate) - Date.now()) / 60000

			if (timeInMin > 59) {
				timeInMin /= 60
				unit = 'Hours'
			}

			if (timeInMin > 23) {
				timeInMin /= 24
				unit = 'Days'
			}

			if (timeInMin > 355) {
				timeInMin /= 355
				unit = 'Years'
			}

			return Math.round(timeInMin) + ' ' + unit
		},
		card() {
			return this.$store.getters.cardById(this.id)
		}
	}
}
</script>

<style lang="scss" scoped>
	.badges {
		display: flex;
		flex-grow: 1;
		.icon {
			opacity: 0.5;
			padding: 12px 3px;
			background-position: left;
			span {
				margin-left: 18px;
			}
		}
		.card-assigned-users {
			flex-grow: 1;
			padding: 6px 0;
			margin-right: -5px;
			.avatardiv {
				float: right;
			}
		}
	}

	.badges .icon.due {
		background-position: 4px center;
		border-radius: 3px;
		margin: 10px 0;
		margin-right: 3px;
		padding: 4px;
		font-size: 90%;
		display: flex;
		align-items: center;
		opacity: .7;

		.icon {
			background-size: contain;
		}

		&.overdue {
			background-color: var(--color-error);
			color: var(--color-primary-text);
		}
		&.now {
			background-color: var(--color-warning);
		}
		&.next {
			background-color: var(--color-warning-light);
		}

		span {
			margin-left: 20px;
		}
	}
</style>
