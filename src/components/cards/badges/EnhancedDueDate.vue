<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
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
import moment from '@nextcloud/moment'
import Clock from 'vue-material-design-icons/Clock.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import { 
	isPersianLocale, 
	toJalali, 
	getRelativeTime,
	formatJalaliDate,
	isJalaliToday,
	isJalaliTomorrow
} from '../../../helpers/jalaliCalendar.js'

const DueState = {
	Done: 'Done',
	Future: 'Future',
	Next: 'Next',
	Now: 'Now',
	Overdue: 'Overdue',
}

export default {
	name: 'EnhancedDueDate',
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
			
			const calendarType = isPersianLocale() ? 'jalali' : 'gregorian'
			
			if (calendarType === 'jalali') {
				// Use Jalali calendar for Persian locale
				const jalaliDueDate = toJalali(this.card.duedate)
				const jalaliToday = moment().jMoment()
				const days = jalaliDueDate.diff(jalaliToday, 'days')
				
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
			} else {
				// Use Gregorian calendar for other locales
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
			}
		},
		overdue() {
			return this.dueState === DueState.Overdue
		},
		relativeDate() {
			const date = this.card.done ? this.card.done : this.card.duedate
			const calendarType = isPersianLocale() ? 'jalali' : 'gregorian'
			
			if (calendarType === 'jalali') {
				// Use Jalali calendar for Persian locale
				if (this.card.done) {
					return getRelativeTime(date)
				}
				
				const jalaliDate = toJalali(date)
				const jalaliToday = moment().jMoment()
				const diff = jalaliToday.diff(jalaliDate, 'seconds')
				
				if (diff >= 0 && diff < 45) {
					return t('core', 'seconds ago')
				}
				
				return getRelativeTime(date)
			} else {
				// Use Gregorian calendar for other locales
				const diff = moment(this.$root.time).diff(date, 'seconds')
				if (diff >= 0 && diff < 45) {
					return t('core', 'seconds ago')
				}
				return moment(date).fromNow()
			}
		},
		absoluteDate() {
			const date = this.card.done ? this.card.done : this.card.duedate
			const calendarType = isPersianLocale() ? 'jalali' : 'gregorian'
			
			if (calendarType === 'jalali') {
				// Use Jalali calendar for Persian locale
				const jalaliDate = toJalali(date)
				const time = moment(date).format('HH:mm')
				
				if (isJalaliToday(date)) {
					return `امروز ساعت ${time}`
				} else if (isJalaliTomorrow(date)) {
					return `فردا ساعت ${time}`
				} else {
					return `${formatJalaliDate(date, 'jYYYY jMMMM jD')} ساعت ${time}`
				}
			} else {
				// Use Gregorian calendar for other locales
				return moment(date).format('LLLL')
			}
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
