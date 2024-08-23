<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<CardDetailEntry :label="t('deck', 'Assign a due date to this card…')" data-test="due-date-selector">
		<Calendar v-if="!card.done" slot="icon" :size="20" />
		<CalendarCheck v-else slot="icon" :size="20" />
		<template v-if="!card.done && !card.archived">
			<NcDateTimePickerNative v-if="duedate"
				id="card-duedate-picker"
				v-model="duedate"
				:placeholder="t('deck', 'Set a due date')"
				:hide-label="true"
				type="datetime-local" />
			<NcActions v-if="canEdit"
				:menu-title="!duedate ? t('deck', 'Add due date') : null"
				type="tertiary"
				data-cy-due-date-actions>
				<template v-if="!duedate" #icon>
					<Plus :size="20" />
				</template>
				<NcActionButton v-for="shortcut in reminderOptions"
					:key="shortcut.key"
					close-after-click
					:data-cy-due-date-shortcut="shortcut.key"
					@click="() => selectShortcut(shortcut)">
					{{ shortcut.label }}
				</NcActionButton>
				<NcActionSeparator />

				<NcActionButton v-if="!duedate"
					close-after-click
					data-cy-due-date-pick
					@click="initDate">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('deck', 'Choose a date') }}
				</NcActionButton>
				<NcActionButton v-else
					icon="icon-delete"
					close-after-click
					data-cy-due-date-remove
					@click="removeDue">
					{{ t('deck', 'Remove due date') }}
				</NcActionButton>
			</NcActions>

			<NcButton v-if="!card.done"
				type="secondary"
				class="completed-button"
				@click="changeCardDoneStatus()">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
				{{ t('deck', 'Mark as done') }}
			</NcButton>
		</template>
		<template v-else>
			<div class="done-info">
				<span v-if="card.done" class="done-info--done">
					{{ formatReadableDate(card.done) }}
				</span>
				<span v-if="duedate" class="done-info--duedate" :class="{ 'dimmed': card.done }">
					{{ t('deck', 'Due at:') }}
					{{ formatReadableDate(duedate) }}
				</span>
			</div>
			<div class="due-actions">
				<NcButton v-if="!card.archived"
					type="tertiary"
					:name="t('deck', 'Not done')"
					@click="changeCardDoneStatus()">
					<template #icon>
						<ClearIcon :size="20" />
					</template>
				</NcButton>
				<NcButton type="secondary" @click="archiveUnarchiveCard()">
					<template #icon>
						<ArchiveIcon :size="20" />
					</template>
					{{ card.archived ? t('deck', 'Unarchive card') : t('deck', 'Archive card') }}
				</NcButton>
			</div>
		</template>
	</CardDetailEntry>
</template>

<script>
import { defineComponent } from 'vue'
import {
	NcActionButton,
	NcActions,
	NcActionSeparator,
	NcButton,
	NcDateTimePickerNative,
} from '@nextcloud/vue'
import readableDate from '../../mixins/readableDate.js'
import { getDayNamesMin, getFirstDay, getMonthNamesShort } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Calendar from 'vue-material-design-icons/Calendar.vue'
import CalendarCheck from 'vue-material-design-icons/CalendarCheck.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import ClearIcon from 'vue-material-design-icons/Close.vue'
import CardDetailEntry from './CardDetailEntry.vue'

export default defineComponent({
	name: 'DueDateSelector',
	components: {
		NcButton,
		ArchiveIcon,
		ClearIcon,
		CardDetailEntry,
		Plus,
		Calendar,
		CalendarCheck,
		CheckIcon,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcDateTimePickerNative,
	},
	mixins: [
		readableDate,
	],
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
				this.$emit('input', val ? new Date(val) : null)
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
			this.$emit('change', null)

		},
		selectShortcut(shortcut) {
			this.duedate = shortcut.timestamp
			this.$emit('change', shortcut.timestamp)
		},
		getTimestamp(momentObject) {
			return momentObject?.minute(0).second(0).millisecond(0).toDate() || null
		},
		changeCardDoneStatus() {
			this.$store.dispatch('changeCardDoneStatus', { ...this.card, done: !this.card.done })
		},
		archiveUnarchiveCard() {
			this.$store.dispatch('archiveUnarchiveCard', { ...this.card, archived: !this.card.archived })
		},
	},
})
</script>
<style scoped lang="scss">
.done-info {
	flex-grow: 1;
}

.done-info--duedate,
.done-info--done {
	display: flex;
	&.dimmed {
		color: var(--color-text-maxcontrast);
	}
}

.completed-button {
	margin-left: auto;
}

.due-actions {
	display: flex;
	align-items: flex-start;
}
</style>
