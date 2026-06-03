<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="gantt-wrapper">
		<div v-if="ganttTasks.length" class="gantt-toolbar">
			<NcButton v-for="mode in viewModes"
				:key="mode.value"
				:type="currentViewMode === mode.value ? 'primary' : 'secondary'"
				@click="changeViewMode(mode.value)">
				{{ mode.label }}
			</NcButton>
		</div>

		<div v-if="ganttTasks.length > 0" ref="ganttContainer" class="gantt-chart" />

		<NcEmptyContent v-if="!ganttTasks.length && !undatedCards.length">
			<template #icon>
				<ChartGanttIcon />
			</template>
			<template #name>
				{{ t('deck', 'No cards yet') }}
			</template>
			<template #description>
				{{ t('deck', 'Set a start date and due date on your cards to see them on the Gantt chart') }}
			</template>
		</NcEmptyContent>

		<div v-if="undatedCards.length" class="gantt-undated">
			<NcButton type="tertiary" @click="showUndated = !showUndated">
				<template #icon>
					<ChevronDown v-if="showUndated" :size="20" />
					<ChevronRight v-else :size="20" />
				</template>
				{{ t('deck', 'Cards without dates ({count})', { count: undatedCards.length }) }}
			</NcButton>
			<div v-if="showUndated" class="gantt-undated__list">
				<div v-for="card in undatedCards"
					:key="card.id"
					class="gantt-undated__card"
					@click="openCard(card)">
					<span class="gantt-undated__stack-dot" :style="{ backgroundColor: getStackColor(card.stackId) }" />
					<span class="gantt-undated__title">{{ card.title }}</span>
					<span class="gantt-undated__stack-name">{{ getStackTitle(card.stackId) }}</span>
				</div>
			</div>
		</div>

		<div v-if="stacks.length" class="gantt-legend">
			<span v-for="stack in stacks"
				:key="stack.id"
				class="gantt-legend__item">
				<span class="gantt-legend__dot" :style="{ backgroundColor: getStackColor(stack.id) }" />
				{{ stack.title }}
			</span>
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import Gantt from 'frappe-gantt'
import 'frappe-gantt/dist/frappe-gantt.css' // eslint-disable-line
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import ChartGanttIcon from 'vue-material-design-icons/ChartGantt.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'

const STACK_COLORS = [
	'#0082c9', '#4caf50', '#ff9800', '#e91e63',
	'#9c27b0', '#00bcd4', '#795548', '#607d8b',
	'#3f51b5', '#8bc34a', '#ff5722', '#009688',
]

// Mirrors frappe-gantt date_utils.convert_scales() constants.
// days_per_unit[scale] = how many days one unit of that scale is.
const DAYS_PER_UNIT = {
	millisecond: 1 / 86400000,
	second: 1 / 86400,
	minute: 1 / 1440,
	hour: 1 / 24,
	day: 1,
	month: 30,
	year: 365,
}

const GANTT_VIEW_MODES = [
	{
		name: 'Day',
		padding: '14d',
		step: '12h',
		snap_at: '12h',
		column_width: 38,
		date_format: 'YYYY-MM-DD',
		lower_text(date, last, lang) {
			if (last && date.getDate() === last.getDate()) return ''
			const day = date.getDate()
			const weekday = new Intl.DateTimeFormat(lang || 'en', { weekday: 'short' }).format(date)
			return day + '\n' + weekday
		},
		upper_text(date, last, lang) {
			if (last && date.getMonth() === last.getMonth()) return ''
			return new Intl.DateTimeFormat(lang || 'en', { month: 'long', year: 'numeric' }).format(date)
		},
		thick_line(date) {
			return date.getDay() === 1
		},
	},
	{
		name: 'Hour',
		padding: '7d',
		step: '1h',
		snap_at: '1h',
		date_format: 'YYYY-MM-DD HH:',
		lower_text: 'HH',
		upper_text: (d, ld, lang) =>
			!ld || d.getDate() !== ld.getDate()
				? Intl.DateTimeFormat(lang || 'en', { month: 'short', day: 'numeric' }).format(d)
				: '',
		thick_line(date) {
			return date.getDay() === 1
		},
		upper_text_frequency: 24,
	},
	{
		name: 'Week',
		padding: '1m',
		step: '7d',
		column_width: 140,
		date_format: 'YYYY-MM-DD',
		lower_text(date, last, lang) {
			const end = new Date(date)
			end.setDate(end.getDate() + 6)
			const sameMonth = date.getMonth() === end.getMonth()
			const fmt = new Intl.DateTimeFormat(lang || 'en', { day: 'numeric', month: 'short' })
			const fmtDay = new Intl.DateTimeFormat(lang || 'en', { day: 'numeric' })
			return fmt.format(date) + ' – ' + (sameMonth ? fmtDay.format(end) : fmt.format(end))
		},
		upper_text(date, last, lang) {
			if (last && date.getMonth() === last.getMonth()) return ''
			return new Intl.DateTimeFormat(lang || 'en', { month: 'long', year: 'numeric' }).format(date)
		},
		thick_line(date) {
			return date.getDate() >= 1 && date.getDate() <= 7
		},
		upper_text_frequency: 4,
	},
	{
		name: 'Month',
		padding: '2m',
		step: '1m',
		column_width: 120,
		date_format: 'YYYY-MM',
		lower_text: 'MMMM',
		upper_text(date, last, lang) {
			if (last && date.getFullYear() === last.getFullYear()) return ''
			return new Intl.DateTimeFormat(lang || 'en', { year: 'numeric' }).format(date)
		},
		thick_line(date) {
			return date.getMonth() % 3 === 0
		},
		snap_at: '7d',
	},
]

export default {
	name: 'GanttView',
	components: {
	       NcButton,
	       NcEmptyContent,
	       ChartGanttIcon,
	       ChevronDown,
	       ChevronRight,
	},
	props: {
		board: {
			type: Object,
			required: true,
		},
		stacks: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			ganttInstance: null,
			currentViewMode: 'Day',
			pendingChange: null,
			showUndated: false,
			viewModes: [
				{ value: 'Hour', label: t('deck', 'Hours') },
				{ value: 'Day', label: t('deck', 'Days') },
				{ value: 'Week', label: t('deck', 'Weeks') },
				{ value: 'Month', label: t('deck', 'Months') },
			],
		}
	},
	computed: {
		...mapGetters(['cardsByStack', 'canEdit']),
		partitionedCards() {
			const undatedCards = []
			const ganttTasks = []
			this.stacks.forEach((stack, index) => {
				this.cardsByStack(stack.id).forEach((card) => {
					if (!card.duedate && !card.startdate) {
						undatedCards.push(card)
					} else {
						// gantt renders everything at once so large date ranges cause performance issues on render
						// therefore we limit the timeframe of visible tasks
						const duedate = new Date(card.duedate)
						switch (this.currentViewMode) {
						case 'Hour':
							if (duedate < new Date() - 2 * 24 * 3600 * 1000) {
								return
							}
							break
						case 'Day':
							if (duedate < new Date() - 30 * 24 * 3600 * 1000) {
								return
							}
							break
						case 'Week':
							if (duedate < new Date() - 90 * 24 * 3600 * 1000) {
								return
							}
							break
						case 'Month':
							if (duedate < new Date() - 365 * 24 * 3600 * 1000) {
								return
							}
							break
						}
						ganttTasks.push(this.cardToGanttTask(card, index))
					}
				})
			})
			return { undatedCards, ganttTasks }
		},
		ganttTasks() {
			return this.partitionedCards.ganttTasks
		},
		undatedCards() {
			return this.partitionedCards.undatedCards
		},
	},
	watch: {
		ganttTasks: {
			deep: true,
			handler(tasks, oldTasks) {
				if (oldTasks.length === 0 && tasks.length > 0) {
					this.$nextTick(() => this.renderGantt())
					return
				}

				// checking pendingChange to only refresh on updates not coming from the chart
				if (!this.pendingChange && this.ganttInstance) {
					const cloned = tasks.map(t => ({ ...t, start: new Date(t.start), end: new Date(t.end) }))
					this.ganttInstance.refresh(cloned)
				}
			},
		},
		currentViewMode(mode) {
			if (this.ganttInstance) {
				this.ganttInstance.change_view_mode(mode)
				this.$nextTick(() => this.fitColumnsToWidth())
			}
		},
	},
	mounted() {
		this._onMouseUp = async (event) => {
			if (this.pendingChange) {
				const { task, start, end } = this.pendingChange
				await this.updateTaskDate(task, start, end)
				this.pendingChange = null
				return
			}

			const barWrapper = event.target.closest('.bar-wrapper')
			if (barWrapper) {
				const taskId = barWrapper.getAttribute('data-id')
				const task = this.ganttTasks.find(t => t.id === taskId)
				if (task) {
					this.openCard(task._card)
				}
			}
		}
		document.addEventListener('mouseup', this._onMouseUp)
		this.$nextTick(() => this.renderGantt())
	},
	beforeDestroy() {
		document.removeEventListener('mouseup', this._onMouseUp)
		this.ganttInstance = null
	},
	methods: {
		cardToGanttTask(card, stackIndex) {
			let start = card.startdate ? new Date(card.startdate) : null
			let end = card.duedate ? new Date(card.duedate) : null

			if (!start && end) {
				start = new Date(end)
				start.setHours(start.getHours() - 1)
			}
			if (start && !end) {
				end = new Date(start)
				end.setHours(end.getHours() + 1)
			}

			// Ensure start <= end
			if (start > end) {
				const tmp = start
				start = end
				end = tmp
			}

			return {
				id: String(card.id),
				name: card.title,
				start,
				end,
				progress: card.done ? 100 : 0,
				custom_class: 'gantt-bar--color-' + (stackIndex % STACK_COLORS.length),
				_card: card,
			}
		},
		getStackColor(stackId) {
			const idx = this.stacks.findIndex(s => s.id === stackId)
			return STACK_COLORS[idx % STACK_COLORS.length] || STACK_COLORS[0]
		},
		getStackTitle(stackId) {
			const stack = this.stacks.find(s => s.id === stackId)
			return stack ? stack.title : ''
		},
		changeViewMode(mode) {
			this.currentViewMode = mode
		},
		openCard(card) {
			this.$router.push({
				name: 'card',
				params: {
					id: this.board.id,
					cardId: card.id,
				},
			})
		},
		renderGantt() {
			if (!this.$refs.ganttContainer || this.ganttTasks.length === 0) {
				return
			}

			this.$refs.ganttContainer.innerHTML = ''

			this.ganttInstance = new Gantt(this.$refs.ganttContainer, this.ganttTasks, {
				view_modes: GANTT_VIEW_MODES,
				bar_height: 28,
				lower_header_height: 40,
				padding: 20,
				scroll_to: 'today',
				today_button: true,
				infinite_padding: false,
				readonly_progress: true,
				readonly: !this.canEdit,
				popup: false,
				on_date_change: (task, start, end) => {
					this.pendingChange = { task, start, end }
				},
			})

			this._patchBarDuration()
			this.fitColumnsToWidth()
		},
		async updateTaskDate(task, start, end) {
			await this.$store.dispatch('updateCardDates', {
				...task._card,
				startdate: new Date(start).toISOString(),
				duedate: new Date(end).toISOString(),
			})
		},
		_patchBarDuration() {
			const bars = this.ganttInstance?.bars
			if (!bars?.length) return
			const BarProto = Object.getPrototypeOf(bars[0])
			if (BarProto._deckDurationPatched) return
			BarProto._deckDurationPatched = true

			// we overwrite the compute_duration function because it enforces a minimum of 1 day duration
			// for reference: https://github.com/frappe/gantt/issues/534
			BarProto.compute_duration = function() {
				const ms = this.task._end - this.task._start
				const unit = this.gantt.config.unit
				const step = this.gantt.config.step

				// In Hour + Day view use full millisecond precision so sub-day tasks
				// render at their true duration. In all other views enforce a
				// minimum of 1 day so short tasks remain visible.
				const durationInDays = this.gantt.config.view_mode.name === 'Hour' || this.gantt.config.view_mode.name === 'Day'
					? ms / 86400000
					: Math.max(1, ms / 86400000)

				this.task.actual_duration = Math.ceil(durationInDays)
				this.task.ignored_duration = 0
				this.duration = (durationInDays / DAYS_PER_UNIT[unit]) / step
				this.actual_duration_raw = this.duration
				this.ignored_duration_raw = 0
			}
		},
		fitColumnsToWidth() {
			const gantt = this.ganttInstance
			if (!gantt?.dates?.length) return
			const containerWidth = this.$refs.ganttContainer.clientWidth
			const contentWidth = gantt.dates.length * gantt.config.column_width
			if (contentWidth < containerWidth) {
				const fitted = Math.floor(containerWidth / gantt.dates.length)
				gantt.config.view_mode.column_width = fitted
				gantt.change_view_mode(gantt.config.view_mode.name, true)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.gantt-wrapper {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: 0;
	overflow: hidden;
	padding: 0 var(--default-grid-baseline);
}

.gantt-toolbar {
	flex: 0 0 auto;
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2);
}

.gantt-chart {
	flex: 1 1 0;
	width: 100%;
	min-height: 0;
	overflow-x: auto;
	overflow-y: hidden;
}

.gantt-undated {
	flex: 0 0 auto;
	padding: calc(var(--default-grid-baseline) * 2);
	border-top: 1px solid var(--color-border);
}

.gantt-undated__list {
	max-height: 200px;
	overflow-y: auto;
}

.gantt-undated__card {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2);
	cursor: pointer;
	border-radius: var(--border-radius);

	&:hover {
		background: var(--color-background-hover);
	}
}

.gantt-undated__stack-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	flex-shrink: 0;
}

.gantt-undated__title {
	flex-grow: 1;
}

.gantt-undated__stack-name {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.gantt-legend {
	flex: 0 0 auto;
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 3);
	padding: calc(var(--default-grid-baseline) * 2);
	border-top: 1px solid var(--color-border);
}

.gantt-legend__item {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	font-size: 0.9em;
}

.gantt-legend__dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
}
</style>

<style lang="scss">
@use 'sass:list';

/* Map frappe-gantt CSS variables to Nextcloud theme variables */
.gantt-chart .gantt-container {
	max-height: 100%;
	/* Grid and background */
	--g-row-color: var(--color-main-background);
	--g-header-background: var(--color-background-dark);
	--g-border-color: var(--color-border);
	--g-row-border-color: var(--color-border);
	--g-tick-color: var(--color-border);
	--g-tick-color-thick: var(--color-border);
	--g-weekend-highlight-color: var(--color-background-hover);

	/* Text */
	--g-text-dark: var(--color-main-text);
	--g-text-light: var(--color-main-text);
	--g-text-muted: var(--color-text-maxcontrast);
	--g-weekend-label-color: var(--color-text-maxcontrast);

	/* Today highlight */
	--g-today-highlight: var(--color-primary-element);

	/* Actions / buttons */
	--g-actions-background: var(--color-background-dark);

	/* Bars */
	--g-bar-color: var(--color-primary-element-light);
	--g-progress-color: var(--color-primary-element);
	--g-handle-color: var(--color-primary-element);

}

/* Move the Today button up into the upper header area */
.gantt-chart .gantt-container .side-header {
	padding-top: 2px;

	button {
		color: var(--color-main-text);
		border-color: var(--color-border);
	}
}

/* Bar handle visibility */
.gantt-chart .gantt {
	.handle {
		visibility: hidden;
	}
	.bar-wrapper:hover .handle {
		visibility: visible;
	}

	// Stack color classes — bar fill and progress colors
	$stack-colors: '#0082c9', '#4caf50', '#ff9800', '#e91e63',
		'#9c27b0', '#00bcd4', '#795548', '#607d8b',
		'#3f51b5', '#8bc34a', '#ff5722', '#009688';

	@each $color in $stack-colors {
		$i: list.index($stack-colors, $color) - 1;

		.bar-wrapper.gantt-bar--color-#{$i} {
			.bar {
				fill: #{$color}33 !important;
				stroke: #{$color} !important;
				stroke-width: 1;
			}
			.bar-progress {
				fill: #{$color} !important;
			}
		}
	}
}
</style>
