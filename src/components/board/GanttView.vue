<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="gantt-wrapper">
		<div class="gantt-toolbar">
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
				{{ t('deck', 'No cards with dates') }}
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

		<div v-if="stacksByBoard.length" class="gantt-legend">
			<span v-for="stack in stacksByBoard"
				:key="stack.id"
				class="gantt-legend__item">
				<span class="gantt-legend__dot" :style="{ backgroundColor: getStackColor(stack.id) }" />
				{{ stack.title }}
			</span>
		</div>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import Gantt from 'frappe-gantt'
import 'frappe-gantt/dist/frappe-gantt.css' // eslint-disable-line
import chroma from 'chroma-js'
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import ChartGanttIcon from 'vue-material-design-icons/ChartGantt.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'

const STACK_COLORS = [
	'#0082c9', '#4caf50', '#ff9800', '#e91e63',
	'#9c27b0', '#00bcd4', '#795548', '#607d8b',
	'#3f51b5', '#8bc34a', '#ff5722', '#009688',
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
			isDragging: false,
			showUndated: false,
			viewModes: [
				{ value: 'Day', label: t('deck', 'Days') },
				{ value: 'Week', label: t('deck', 'Weeks') },
				{ value: 'Month', label: t('deck', 'Months') },
			],
		}
	},
	computed: {
		...mapState({
			filter: state => state.filter,
		}),
		ganttViewModes() {
			return [
				{
					name: 'Day',
					padding: '14d',
					step: '1d',
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
		},
		stacksByBoard() {
			return this.stacks || []
		},
		allCards() {
			const cards = []
			for (const stack of this.stacksByBoard) {
				const stackCards = this.$store.getters.cardsByStack(stack.id)
				cards.push(...stackCards)
			}
			return cards
		},
		ganttTasks() {
			return this.allCards
				.filter(card => card.duedate || card.startdate)
				.map(card => {
					const now = new Date()
					let start = card.startdate ? new Date(card.startdate) : null
					let end = card.duedate ? new Date(card.duedate) : null

					if (!start && end) {
						start = new Date(end)
						start.setDate(start.getDate() - 1)
					}
					if (start && !end) {
						end = new Date(start)
						end.setDate(end.getDate() + 1)
					}
					if (!start && !end) {
						start = now
						end = new Date(now)
						end.setDate(end.getDate() + 1)
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
						start: this.formatDate(start),
						end: this.formatDate(end),
						progress: card.done ? 100 : 0,
						color: this.getStackColor(card.stackId),
						custom_class: 'gantt-bar--stack-' + card.stackId,
						_card: card,
					}
				})
		},
		undatedCards() {
			return this.allCards.filter(card => !card.duedate && !card.startdate)
		},
	},
	watch: {
		ganttTasks: {
			deep: true,
			handler() {
				if (!this.isDragging) {
					this.$nextTick(() => this.renderGantt())
				}
			},
		},
		currentViewMode(mode) {
			if (this.ganttInstance) {
				this.ganttInstance.change_view_mode(mode)
				this.$nextTick(() => this.fitColumnsToWidth())
			} else {
				this.$nextTick(() => this.renderGantt())
			}
		},
	},
	mounted() {
		this._onMouseUp = () => {
			if (this._pendingChange) {
				const { task, start, end } = this._pendingChange
				this._pendingChange = null
				this.onDateChange(task, start, end)
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
		formatDate(date) {
			const y = date.getFullYear()
			const m = String(date.getMonth() + 1).padStart(2, '0')
			const d = String(date.getDate()).padStart(2, '0')
			return `${y}-${m}-${d}`
		},
		getStackColor(stackId) {
			const idx = this.stacksByBoard.findIndex(s => s.id === stackId)
			return STACK_COLORS[idx % STACK_COLORS.length] || STACK_COLORS[0]
		},
		getStackTitle(stackId) {
			const stack = this.stacksByBoard.find(s => s.id === stackId)
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

			// Clear previous
			this.$refs.ganttContainer.innerHTML = ''

			// Inject dynamic stack colors as CSS
			this.injectStackStyles()

			this.ganttInstance = new Gantt(this.$refs.ganttContainer, this.ganttTasks, {
				view_mode: this.currentViewMode,
				view_modes: this.ganttViewModes,
				bar_height: 28,
				lower_header_height: 40,
				padding: 20,
				scroll_to: 'today',
				today_button: true,
				infinite_padding: false,
				on_click: (task) => {
					this.openCard(task._card)
				},
				on_date_change: (task, start, end) => {
					// Track the latest drag state; actual save happens on mouseup
					this.isDragging = true
					this._pendingChange = { task, start, end }
				},
			})

			this.fitColumnsToWidth()
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
		async onDateChange(task, start, end) {
			const card = task._card
			if (!card) return

			try {
				await this.$store.dispatch('updateCardStartDate', {
					...card,
					startdate: new Date(start).toISOString(),
				})
				await this.$store.dispatch('updateCardDue', {
					...card,
					duedate: new Date(end).toISOString(),
				})
			} finally {
				this.isDragging = false
			}
		},
		injectStackStyles() {
			const styleId = 'gantt-stack-styles'
			let styleEl = document.getElementById(styleId)
			if (!styleEl) {
				styleEl = document.createElement('style')
				styleEl.id = styleId
				document.head.appendChild(styleEl)
			}

			const rules = this.stacksByBoard.map((stack, idx) => {
				const color = STACK_COLORS[idx % STACK_COLORS.length]
				const lightColor = chroma(color).alpha(0.3).css()
				const darkColor = chroma(color).darken(0.5).css()
				return `.gantt .bar-wrapper.gantt-bar--stack-${stack.id} .bar-progress { fill: ${darkColor} !important; }
.gantt .bar-wrapper.gantt-bar--stack-${stack.id} .bar { fill: ${lightColor} !important; stroke: ${color} !important; stroke-width: 1; }`
			}).join('\n')

			styleEl.textContent = rules
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
/* Override frappe-gantt's inner container to prevent vertical scrollbar */
.gantt-chart > .gantt-container {
	overflow-y: hidden !important;
}

/* Move the Today button up into the upper header area */
.gantt-chart .gantt-container .side-header {
	padding-top: 2px;
}

/* Unscoped styles for Frappe Gantt SVG elements */
.gantt-chart .gantt {
	.grid-header {
		fill: var(--color-background-dark);
	}
	.grid-row {
		fill: var(--color-main-background);
		&:nth-child(even) {
			fill: var(--color-background-hover);
		}
	}
	.row-line {
		stroke: var(--color-border);
	}
	.tick {
		stroke: var(--color-border);
	}
	.today-highlight {
		fill: var(--color-primary-element-light);
		opacity: 0.3;
	}
	.bar-label {
		font-size: 12px;
		fill: var(--color-main-text);
	}
	.upper-text, .lower-text {
		fill: var(--color-text-maxcontrast);
		font-size: 12px;
	}
	.handle {
		visibility: hidden;
	}
	.bar-wrapper:hover .handle {
		visibility: visible;
	}
}
</style>
