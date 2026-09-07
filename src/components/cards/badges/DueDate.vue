<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
import Clock from 'vue-material-design-icons/Clock.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import { useFormatTime, useFormatRelativeTime } from '@nextcloud/vue'

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
			const days = Math.floor((new Date(this.card.duedate).getTime() - new Date(this.$root.time).getTime()) / 60 / 60 / 24 / 1000)
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
			return useFormatRelativeTime(this.card.done ? this.card.done : this.card.duedate).value
		},
		absoluteDate() {
			const date = new Date(this.card.done ? this.card.done : this.card.duedate)
			return useFormatTime(date, { format: { dateStyle: 'full', timeStyle: 'short' } }).value
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
			color: var(--color-element-error, var(--color-error-text));
			background-color: rgba(var(--color-error-rgb), .5);
		}
		[data-due-state='Now'] & {
			color: var(--color-element-warning, var(--color-warning-text));
			background-color: rgba(var(--color-warning-rgb), .5);
		}
		[data-due-state='Done'] & {
			color: var(--color-element-success, var(--color-success-text));
			background-color: rgba(var(--color-success-rgb), .5);
		}

		.due--label {
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
			margin-inline-start: 4px;
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
