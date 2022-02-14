<template>
	<div v-if="activeTab === 'duedate' || (copiedCard && copiedCard.duedate)"
		v-show="!['project', 'attachment'].includes(activeTab)"
		class="section-details">
		<div @click="$emit('active-tab', 'duedate')">
			<DatetimePicker v-model="duedate"
				:placeholder="t('deck', 'Set a due date')"
				type="datetime"
				:minute-step="5"
				:show-second="false"
				:lang="lang"
				:disabled="saving || !canEdit"
				:shortcuts="shortcuts"
				confirm />
		</div>
		<Actions v-if="canEdit">
			<ActionButton v-if="copiedCard.duedate" icon="icon-delete" @click="removeDue()">
				{{ t('deck', 'Remove due date') }}
			</ActionButton>
		</Actions>
	</div>
</template>

<script>
import { DatetimePicker, Actions, ActionButton } from '@nextcloud/vue'
import { mapState, mapGetters } from 'vuex'
import Color from '../../mixins/color'
import labelStyle from '../../mixins/labelStyle'
import {
	getDayNamesMin,
	getFirstDay,
	getMonthNamesShort,
} from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

export default {
	components: { DatetimePicker, Actions, ActionButton },
	mixins: [Color, labelStyle],
	props: {
		card: {
			type: Object,
			default: null,
		},
		activeTab: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
			saving: false,
			copiedCard: null,
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
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters(['canEdit']),
		labelsSorted() {
			return [...this.currentBoard.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
		duedate: {
			get() {
				return this.card.duedate ? new Date(this.card.duedate) : null
			},
			async set(val) {
				this.saving = true
				await this.$store.dispatch('updateCardDue', {
					...this.copiedCard,
					duedate: val ? moment(val).format('YYYY-MM-DD H:mm:ss') : null,
				})
				this.saving = false
			},
		},
	},
	watch: {
		card() {
			this.initialize()
		},
	},
	mounted() {
		this.initialize()
	},
	methods: {
		async initialize() {
			if (!this.card) {
				return
			}

			this.copiedCard = JSON.parse(JSON.stringify(this.card))
		},
		removeDue() {
			this.copiedCard.duedate = null
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},
	},
}
</script>

<style lang="scss" scoped>
.section-details {
	margin-right: 5px;
	display: flex;
	align-items: flex-start;
}
</style>

<style>
.section-details .mx-input {
	height: 36px !important;
	margin: 0;
}

.section-details .action-item {
	height: 30px !important;
}
</style>
