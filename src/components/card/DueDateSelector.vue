<template>
	<div class="selector-wrapper" :aria-label="t('deck', 'Assign a due date to this card…')">
		<div class="selector-wrapper--icon">
			<Calendar :size="20" />
		</div>
		<div class="duedate-selector">
			<NcDatetimePicker v-model="duedate"
				:placeholder="t('deck', 'Set a due date')"
				type="datetime"
				:minute-step="5"
				:show-second="false"
				:lang="lang"
				:formatter="format"
				:disabled="!canEdit"
				:shortcuts="shortcuts"
				:append-to-body="true"
				confirm />
			<NcActions v-if="canEdit">
				<NcActionButton v-if="duedate" icon="icon-delete" @click="removeDue()">
					{{ t('deck', 'Remove due date') }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { defineComponent } from 'vue'
import { NcActionButton, NcActions, NcDatetimePicker } from '@nextcloud/vue'
import { getDayNamesMin, getFirstDay, getMonthNamesShort } from '@nextcloud/l10n'
import Calendar from 'vue-material-design-icons/Calendar.vue'
import readableDate from '../../mixins/readableDate.js'

export default defineComponent({
	name: 'DueDateSelector',
	components: {
		Calendar,
		NcActions,
		NcActionButton,
		NcDatetimePicker,
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
			shortcuts: [
				{
					text: t('deck', 'Today'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate())
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
				{
					text: t('deck', 'Tomorrow'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate() + 1)
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
				{
					text: t('deck', 'Next week'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate() + 7)
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
				{
					text: t('deck', 'Next month'),
					onClick() {
						const date = new Date()
						date.setDate(date.getDate() + 30)
						date.setHours(23)
						date.setMinutes(59)
						return date
					},
				},
			],
		}
	},
	computed: {
		duedate: {
			get() {
				return this.card.duedate ? new Date(this.card.duedate) : null
			},
			set(val) {
				this.$emit('change', val)
			},
		},
	},
	methods: {
		removeDue() {
			this.duedate = null
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
