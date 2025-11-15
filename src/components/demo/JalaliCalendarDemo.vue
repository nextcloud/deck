<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="jalali-calendar-demo">
		<h2>{{ t('deck', 'Jalali Calendar Demo') }}</h2>
		
		<div class="demo-section">
			<h3>{{ t('deck', 'Current Calendar Type') }}</h3>
			<p>{{ t('deck', 'Calendar Type:') }} <strong>{{ currentCalendarType }}</strong></p>
			<p>{{ t('deck', 'Is Persian Locale:') }} <strong>{{ isPersianCalendar ? 'Yes' : 'No' }}</strong></p>
		</div>

		<div class="demo-section">
			<h3>{{ t('deck', 'Date Conversions') }}</h3>
			<div class="conversion-example">
				<p><strong>{{ t('deck', 'Gregorian Date:') }}</strong> {{ gregorianDate }}</p>
				<p><strong>{{ t('deck', 'Jalali Date:') }}</strong> {{ jalaliDate }}</p>
				<p><strong>{{ t('deck', 'Formatted Jalali:') }}</strong> {{ formattedJalaliDate }}</p>
			</div>
		</div>

		<div class="demo-section">
			<h3>{{ t('deck', 'Persian Calendar Data') }}</h3>
			<div class="calendar-data">
				<div class="data-column">
					<h4>{{ t('deck', 'Day Names') }}</h4>
					<ul>
						<li v-for="(day, index) in persianDayNames" :key="index">
							{{ day }}
						</li>
					</ul>
				</div>
				<div class="data-column">
					<h4>{{ t('deck', 'Month Names') }}</h4>
					<ul>
						<li v-for="(month, index) in persianMonthNames" :key="index">
							{{ month }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="demo-section">
			<h3>{{ t('deck', 'Date Formatting Examples') }}</h3>
			<div class="formatting-examples">
				<p><strong>{{ t('deck', 'Enhanced Date:') }}</strong> {{ enhancedDate }}</p>
				<p><strong>{{ t('deck', 'Readable Date:') }}</strong> {{ readableDate }}</p>
				<p><strong>{{ t('deck', 'Relative Date:') }}</strong> {{ relativeDate }}</p>
				<p><strong>{{ t('deck', 'Date for Display:') }}</strong> {{ dateForDisplay }}</p>
				<p><strong>{{ t('deck', 'Time for Display:') }}</strong> {{ timeForDisplay }}</p>
				<p><strong>{{ t('deck', 'Date Time for Display:') }}</strong> {{ dateTimeForDisplay }}</p>
			</div>
		</div>

		<div class="demo-section">
			<h3>{{ t('deck', 'Interactive Date Picker') }}</h3>
			<EnhancedDueDateSelector 
				:card="demoCard" 
				:can-edit="true" 
				@change="handleDateChange" 
			/>
		</div>

		<div class="demo-section">
			<h3>{{ t('deck', 'Due Date Badge') }}</h3>
			<EnhancedDueDate :card="demoCard" />
		</div>
	</div>
</template>

<script>
import { defineComponent } from 'vue'
import EnhancedDueDateSelector from '../card/EnhancedDueDateSelector.vue'
import EnhancedDueDate from '../cards/badges/EnhancedDueDate.vue'
import enhancedReadableDate from '../../mixins/enhancedReadableDate.js'
import { 
	getCurrentCalendarType,
	isPersianLocale,
	toJalali,
	formatJalaliDate,
	getPersianDayNames,
	getPersianMonthNames,
	getPersianShortDayNames,
	getPersianShortMonthNames
} from '../../helpers/jalaliCalendar.js'

export default defineComponent({
	name: 'JalaliCalendarDemo',
	components: {
		EnhancedDueDateSelector,
		EnhancedDueDate
	},
	mixins: [enhancedReadableDate],
	data() {
		return {
			demoCard: {
				id: 1,
				title: 'Demo Card',
				duedate: new Date(Date.now() + 24 * 60 * 60 * 1000), // Tomorrow
				done: false,
				archived: false
			}
		}
	},
	computed: {
		currentCalendarType() {
			return getCurrentCalendarType()
		},
		isPersianCalendar() {
			return isPersianLocale()
		},
		gregorianDate() {
			return new Date().toLocaleDateString()
		},
		jalaliDate() {
			const jalali = toJalali(new Date())
			return jalali ? jalali.format('jYYYY/jM/jD') : 'N/A'
		},
		formattedJalaliDate() {
			return formatJalaliDate(new Date(), 'jYYYY jMMMM jD')
		},
		persianDayNames() {
			return getPersianDayNames()
		},
		persianMonthNames() {
			return getPersianMonthNames()
		},
		enhancedDate() {
			return this.formatEnhancedDate(new Date())
		},
		readableDate() {
			return this.formatReadableDate(new Date())
		},
		relativeDate() {
			return this.formatRelativeDate(new Date())
		},
		dateForDisplay() {
			return this.formatDateForDisplay(new Date())
		},
		timeForDisplay() {
			return this.formatTimeForDisplay(new Date())
		},
		dateTimeForDisplay() {
			return this.formatDateTimeForDisplay(new Date())
		}
	},
	methods: {
		handleDateChange(newDate) {
			this.demoCard.duedate = newDate
			console.log('Date changed to:', newDate)
		}
	}
})
</script>

<style scoped lang="scss">
.jalali-calendar-demo {
	max-width: 800px;
	margin: 0 auto;
	padding: 20px;
	font-family: var(--font-face, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
}

.demo-section {
	margin-bottom: 30px;
	padding: 20px;
	border: 1px solid var(--color-border, #ddd);
	border-radius: 8px;
	background-color: var(--color-background-hover, #f8f9fa);

	h3 {
		margin-top: 0;
		color: var(--color-primary, #0082c9);
		border-bottom: 2px solid var(--color-primary, #0082c9);
		padding-bottom: 10px;
	}

	h4 {
		color: var(--color-text, #333);
		margin-bottom: 10px;
	}
}

.conversion-example {
	background-color: var(--color-background, #fff);
	padding: 15px;
	border-radius: 6px;
	border-left: 4px solid var(--color-primary, #0082c9);

	p {
		margin: 8px 0;
		font-family: 'Courier New', monospace;
	}
}

.calendar-data {
	display: flex;
	gap: 20px;

	.data-column {
		flex: 1;
		background-color: var(--color-background, #fff);
		padding: 15px;
		border-radius: 6px;

		ul {
			list-style: none;
			padding: 0;
			margin: 0;

			li {
				padding: 5px 0;
				border-bottom: 1px solid var(--color-border, #eee);
				font-family: 'Courier New', monospace;

				&:last-child {
					border-bottom: none;
				}
			}
		}
	}
}

.formatting-examples {
	background-color: var(--color-background, #fff);
	padding: 15px;
	border-radius: 6px;
	border-left: 4px solid var(--color-success, #46ba61);

	p {
		margin: 8px 0;
		font-family: 'Courier New', monospace;
	}
}

@media (max-width: 768px) {
	.calendar-data {
		flex-direction: column;
	}

	.jalali-calendar-demo {
		padding: 10px;
	}
}
</style>
