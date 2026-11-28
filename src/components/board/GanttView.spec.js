/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, jest } from '@jest/globals'
import GanttView from './GanttView.vue'

jest.mock('@nextcloud/vue', () => ({}), { virtual: true })
jest.mock('frappe-gantt', () => ({}))
jest.mock('frappe-gantt/dist/frappe-gantt.css', () => ({}), { virtual: true })
jest.mock('pinia', () => ({ mapActions: () => ({}), mapState: () => ({}) }))
jest.mock('vue-material-design-icons/ChartGantt.vue', () => ({}))
jest.mock('vue-material-design-icons/ChevronDown.vue', () => ({}))
jest.mock('vue-material-design-icons/ChevronRight.vue', () => ({}))
jest.mock('../../stores/board.js', () => ({}))
jest.mock('../../stores/card.js', () => ({}))

describe('GanttView card partitioning', () => {
	it('includes a start-only card in Gantt tasks and leaves undated cards in the undated list', () => {
		const startOnlyCard = {
			id: 1,
			title: 'Start only',
			startdate: new Date().toISOString(),
			duedate: null,
		}
		const undatedCard = {
			id: 2,
			title: 'No dates',
			startdate: null,
			duedate: null,
		}
		const cardToGanttTask = jest.fn((card) => ({ id: String(card.id) }))
		const context = {
			stacks: [{ id: 10 }],
			cardsByStack: () => [startOnlyCard, undatedCard],
			currentViewMode: 'Day',
			cardToGanttTask,
		}

		const result = GanttView.computed.partitionedCards.call(context)

		expect(result.ganttTasks).toEqual([{ id: '1' }])
		expect(result.undatedCards).toEqual([undatedCard])
		expect(cardToGanttTask).toHaveBeenCalledWith(startOnlyCard, 0)
	})
})
