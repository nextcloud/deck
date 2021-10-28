<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-if="card" class="duedate">
		<transition name="zoom">
			<div v-if="card.duedate" :class="dueIcon" :title="absoluteDate">
				<span>{{ relativeDate }}</span>
			</div>
		</transition>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'

export default {
	name: 'DueDate',
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	computed: {
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
		relativeDate() {
			const diff = moment(this.$root.time).diff(this.card.duedate, 'seconds')
			if (diff >= 0 && diff < 45) {
				return t('core', 'seconds ago')
			}
			return moment(this.card.duedate).fromNow()
		},
		absoluteDate() {
			return moment(this.card.duedate).format('L')
		},
	},
}
</script>

<style lang="scss" scoped>
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

		&::before,
		span {
			margin-left: 20px;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
		}
	}

	@media print {
		.icon.due {
			background-color: transparent !important;

			span {
				display: none;
			}

			&::before {
				content: attr(title);
			}
		}
	}
</style>
