<template>
	<div class="selector-wrapper" :aria-label="t('deck', 'Assign a due date to this card…')">
		<div class="selector-wrapper--icon">
			<Calendar :size="20" />
		</div>
		<div class="duedate-selector">
			<NcDateTimePickerNative v-if="duedate"
				id="card-duedate-picker"
				v-model="duedate"
				:placeholder="t('deck', 'Set a due date')"
				:hide-label="true"
				type="datetime-local" />
			<NcActions v-if="canEdit" :menu-title="!duedate ? t('deck', 'Add due date') : null" type="tertiary">
				<template v-if="!duedate" #icon>
					<Plus :size="20" />
				</template>
				<NcActionButton v-for="shortcut in reminderOptions"
					:key="shortcut.key"
					close-after-click
					@click="() => selectShortcut(shortcut)">
					{{ shortcut.label }}
				</NcActionButton>
				<NcActionSeparator />

				<NcActionButton v-if="!duedate" close-after-click @click="initDate">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('deck', 'Choose a date') }}
				</NcActionButton>
				<NcActionButton v-else
					icon="icon-delete"
					close-after-click
					@click="removeDue">
					{{ t('deck', 'Remove due date') }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { defineComponent } from 'vue'
import { NcActionButton, NcActions, NcActionSeparator, NcDateTimePickerNative } from '@nextcloud/vue'
import { getDayNamesMin, getFirstDay, getMonthNamesShort } from '@nextcloud/l10n'
import Plus from 'vue-material-design-icons/Plus.vue'
import Calendar from 'vue-material-design-icons/Calendar.vue'
import moment from '@nextcloud/moment'

export default defineComponent({
	name: 'DueDateSelector',
	components: {
		Plus,
		Calendar,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcDateTimePickerNative,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			lang: {
				days: getDayNamesMin(),
				months: getMonthNamesShort(),
				formatLocale: {
					firstDayOfWeek: getFirstDay() === 0 ? 7 : getFirstDay(),
				},
				placeholder: {
					date: t('deck', 'Select Date'),
				},
			},
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
		}
	},
	computed: {
		duedate: {
			get() {
				return this.card?.duedate ? new Date(this.card.duedate) : null
			},
			set(val) {
				this.$emit('change', val ? new Date(val) : null)
			},
		},

		reminderOptions() {
			const currentDateTime = moment()
			// Same day 18:00 PM (or hidden)
			const laterTodayTime = (currentDateTime.hour() < 18)
				? moment().hour(18)
				: null
			// Tomorrow 08:00 AM
			const tomorrowTime = moment().add(1, 'days').hour(8)
			// Saturday 08:00 AM (or hidden)
			const thisWeekendTime = (currentDateTime.day() !== 6 && currentDateTime.day() !== 0)
				? moment().day(6).hour(8)
				: null
			// Next Monday 08:00 AM
			const nextWeekTime = moment().add(1, 'weeks').day(1).hour(8)
			return [
				{
					key: 'laterToday',
					timestamp: this.getTimestamp(laterTodayTime),
					label: t('deck', 'Later today – {timeLocale}', { timeLocale: laterTodayTime?.format('LT') }),
					ariaLabel: t('deck', 'Set due date for later today'),
				},
				{
					key: 'tomorrow',
					timestamp: this.getTimestamp(tomorrowTime),
					label: t('deck', 'Tomorrow – {timeLocale}', { timeLocale: tomorrowTime?.format('ddd LT') }),
					ariaLabel: t('deck', 'Set due date for tomorrow'),
				},
				{
					key: 'thisWeekend',
					timestamp: this.getTimestamp(thisWeekendTime),
					label: t('deck', 'This weekend – {timeLocale}', { timeLocale: thisWeekendTime?.format('ddd LT') }),
					ariaLabel: t('deck', 'Set due date for this weekend'),
				},
				{
					key: 'nextWeek',
					timestamp: this.getTimestamp(nextWeekTime),
					label: t('deck', 'Next week – {timeLocale}', { timeLocale: nextWeekTime?.format('ddd LT') }),
					ariaLabel: t('deck', 'Set due date for next week'),
				},
			].filter(option => option.timestamp !== null)
		},
	},
	methods: {
		initDate() {
			if (this.duedate === null) {
				// We initialize empty dates with a time once clicked to make picking a day easier
				const now = new Date()
				now.setDate(now.getDate() + 1)
				now.setHours(8)
				now.setMinutes(0)
				now.setMilliseconds(0)
				this.duedate = now
			}
		},
		removeDue() {
			this.duedate = null
		},
		selectShortcut(shortcut) {
			this.duedate = shortcut.timestamp
		},
		getTimestamp(momentObject) {
			return momentObject?.minute(0).second(0).millisecond(0).toDate() || null
		},
	},
})
</script>
<style lang="scss">
@import '../../css/selector';

.duedate-selector {
	display: flex;
}
</style>
