cardbadges

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
		<div v-if="card.description" class="card-comments icon icon-edit" />

		<div v-if="card.duedate" :class="dueIcon">
			<span>{{ dueTime }}</span>
		</div>

		<div v-if="card.description && checkListCount > 0" class="card-tasks icon icon-checkmark">
			<span>{{ checkListCheckedCount }}/{{ checkListCount }}</span>
		</div>

		<div v-if="card.attachments" class="card-files icon icon-files-dark" />

		<avatar-list :users="card.assignedUsers" />
	</div>
</template>
<script>
import { Avatar } from 'nextcloud-vue'
import AvatarList from './AvatarList'

export default {
	name: 'CardBadges',
	components: { Avatar, AvatarList },
	props: {
		id: {
			type: Number,
			default: null
		}
	},
	data() {
		return {
			dueTime: null,
			dueIcon: null
		}
	},
	computed: {
		checkListCount() {
			return (this.card.description.match(/\[\s*\x*\]/g) || []).length
		},
		checkListCheckedCount() {
			return (this.card.description.match(/\[\s*x\s*\]/g) || []).length
		},
		compactMode() {
			return false
		},
		card() {
			return this.$store.getters.cardById(this.id)
		}
	},
	created() {
		this.updateDueTime()
		setInterval(this.updateDueTime, 10000)
	},
	destroyed() {
		clearInterval(this.updateDueTime)
	},
	methods: {
		updateDueTime() {
			if (this.card === undefined) {
				return
			}

			if (this.card.duedate === null) {
				return
			}

			this.dueTime = OC.Util.relativeModifiedDate(this.card.duedate)

			let timeInHours = Math.round((Date.parse(this.card.duedate) - Date.now()) / 1000 / 60 / 60 / 24)
			if (timeInHours >= 1) {
				this.dueIcon = 'icon-calendar-dark due icon next'
			}
			if (timeInHours === 0) {
				this.dueIcon = 'icon-calendar-dark due icon now'
			}
			if (timeInHours < 0) {
				this.dueIcon = 'icon-calendar due icon overdue'
			}
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
			&.icon-filetype-text {
				opacity: 1;
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
			background-color: var(--color-warning-light);
			opacity: .7;
		}

		span {
			margin-left: 20px;
		}
	}
</style>
