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
	<div v-if="card" class="duedate" :data-due-state="dueState">
		<transition name="zoom">
			<div v-if="card.duedate || card.done" class="due" :name="absoluteDate">
				<CheckCircle v-if="card.done" :size="16" />
				<Clock v-else-if="overdue" :size="16" />
				<ClockOutline v-else :size="16" />
				<span v-if="!compactMode" class="due--label">{{ relativeDate }}</span>
			</div>
		</transition>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import moment from '@nextcloud/moment'
import Clock from 'vue-material-design-icons/Clock.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'

const DueState = {
	Done: 'Done',
	Future: 'Future',
	Next: 'Next',
	Now: 'Now',
	Overdue: 'Overdue',
}
export default {
	name: 'DueDate',
	components: {
		CheckCircle,
		Clock,
		ClockOutline,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	computed: {
		...mapState({
			compactMode: state => state.compactMode,
		}),
		dueState() {
			if (this.card.done) {
				return DueState.Done
			}
			const days = Math.floor(moment(this.card.duedate).diff(this.$root.time, 'seconds') / 60 / 60 / 24)
			if (days < 0) {
				return DueState.Overdue
			}
			if (days === 0) {
				return DueState.Now
			}
			if (days === 1) {
				return DueState.Next
			}

			return DueState.Future
		},
		overdue() {
			return this.dueState === DueState.Overdue
		},
		relativeDate() {
			const date = this.card.done ? this.card.done : this.card.duedate
			const diff = moment(this.$root.time).diff(date, 'seconds')
			if (diff >= 0 && diff < 45) {
				return t('core', 'seconds ago')
			}
			return moment(date).fromNow()
		},
		absoluteDate() {
			const date = this.card.done ? this.card.done : this.card.duedate
			return moment(date).format('LLLL')
		},
	},
}
</script>

<style lang="scss" scoped>
	.due {
		background-position: 4px center;
		border-radius: var(--border-radius-pill);
		padding: 1px 8px;
		font-size: 90%;
		display: flex;
		align-items: center;
		flex-shrink: 1;
		z-index: 2;

		[data-due-state='Overdue'] & {
			color: var(--color-error-text);
			background-color: rgba(var(--color-error-rgb), .1);
		}
		[data-due-state='Now'] & {
			color: var(--color-warning-text);
			background-color: rgba(var(--color-warning-rgb), .1);
		}
		[data-due-state='Done'] & {
			color: var(--color-success-text);
			background-color: rgba(var(--color-success-rgb), .1);
		}

		.due--label {
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
			margin-left: 4px;
			font-size: 13px;
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
