<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="controls">
		<NcModal v-if="showAddCardModal" class="card-selector" @close="clickHideAddCardModel">
			<CreateNewCardCustomPicker show-created-notice @cancel="clickHideAddCardModel" />
		</NcModal>
		<div v-if="overviewName" class="board-title">
			<div class="board-bullet icon-calendar-dark" />
			<h2 dir="auto">
				{{ overviewName }}
			</h2>
			<NcActions>
				<NcActionButton icon="icon-add" @click="clickShowAddCardModel">
					{{ t('deck', 'Add card') }}
				</NcActionButton>
			</NcActions>
		</div>
		<div v-else-if="board" class="board-title">
			<div :style="{backgroundColor: '#' + board.color}" class="board-bullet" />
			<h2 dir="auto">
				{{ board.title }}
			</h2>
			<p v-if="showArchived">
				({{ t('deck', 'Archived cards') }})
			</p>
		</div>
		<div class="board-actions">
			<SessionList v-if="isNotifyPushEnabled && presentUsers.length"
				:sessions="presentUsers" />
			<div v-if="board && canManage && !showArchived && !board.archived"
				id="stack-add"
				v-click-outside="hideAddStack">
				<NcActions v-if="!isAddStackVisible">
					<NcActionButton @click.stop="showAddStack">
						{{ t('deck', 'Add list') }}
						<template #icon>
							<TableColumnPlusAfter :size="20" />
						</template>
					</NcActionButton>
				</NcActions>
				<form v-else @submit.prevent="addNewStack()">
					<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add list') }}</label>
					<input id="new-stack-input-main"
						v-model="newStackTitle"
						v-focus
						type="text"
						class="no-close"
						:placeholder="t('deck', 'List name')"
						required
						@focus="$store.dispatch('toggleShortcutLock', true)"
						@blur="$store.dispatch('toggleShortcutLock', false)">
					<input :title="t('deck', 'Add list')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</div>
			<template v-if="showSearch">
				<!-- Not type="search": NcTextField only fills the trailing button's icon
					slot when type !== 'search', which leaves the clear button iconless. -->
				<NcTextField id="deck-search-input"
					class="board-search"
					type="text"
					:label="searchLabel"
					:value="searchQuery"
					:title="searchHint || null"
					:show-trailing-button="searchQuery !== ''"
					:trailing-button-label="t('deck', 'Clear search')"
					:aria-describedby="searchHint ? 'deck-search-hint' : null"
					@update:value="setSearchQuery"
					@trailing-button-click="clearSearchQuery"
					@focus="$store.dispatch('toggleShortcutLock', true)"
					@blur="$store.dispatch('toggleShortcutLock', false)" />
				<!-- title is for pointer users, aria-describedby for assistive tech. No double
					announcement: title is only the fallback description per HTML-AAM. -->
				<span v-if="searchHint" id="deck-search-hint" class="hidden-visually">{{ searchHint }}</span>
			</template>
			<div v-if="board" class="board-action-buttons">
				<div class="board-action-buttons__filter">
					<NcPopover :placement="'bottom-end'"
						:aria-label="t('deck', 'Active filters')"
						:name="t('deck', 'Active filters')"
						@show="filterVisible=true"
						@hide="filterVisible=false">
						<!-- We cannot use NcActions here are the popover trigger does not update on reactive icons -->
						<template #trigger>
							<NcButton ref="filterPopover"
								:title="t('deck', 'Apply filter')"
								:aria-label="t('deck', 'Apply filter')"
								class="filter-button"
								:type="isFilterActive ? 'primary' : 'tertiary'">
								<template #icon>
									<FilterIcon v-if="isFilterActive" :size="20" decorative />
									<FilterOffIcon v-else :size="20" decorative />
								</template>
							</NcButton>
						</template>

						<div v-if="filterVisible" class="filter">
							<div v-if="boardViews.length" class="filter--saved-views">
								<h3>{{ t('deck', 'Saved views') }}</h3>
								<div v-for="view in boardViews" :key="view.id" class="filter--saved-view">
									<NcButton type="tertiary"
										class="filter--saved-view-name"
										:title="t('deck', 'Apply view {name}', { name: view.name })"
										@click="applyView(view)">
										<template #icon>
											<BookmarkOutline :size="18" decorative />
										</template>
										{{ view.name }}
									</NcButton>
									<NcButton type="tertiary"
										:aria-label="t('deck', 'Delete view {name}', { name: view.name })"
										@click="removeView(view)">
										<template #icon>
											<TrashCanOutline :size="18" decorative />
										</template>
									</NcButton>
								</div>
							</div>

							<h3>{{ t('deck', 'Filter by tag') }}</h3>
							<div v-for="label in labelsSorted" :key="label.id" class="filter--item">
								<input :id="label.id"
									v-model="filter.tags"
									type="checkbox"
									class="checkbox"
									:value="label.id"
									@change="setFilter">
								<label :for="label.id"><span class="label" :style="labelStyle(label)">{{ label.title }}</span></label>
							</div>

							<h3>{{ t('deck', 'Filter by assigned user') }}</h3>
							<div class="filter--item">
								<input id="unassigned"
									v-model="filter.unassigned"
									type="checkbox"
									class="checkbox"
									value="unassigned"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="unassigned">{{ t('deck', 'Unassigned') }}</label>
							</div>
							<div v-for="user in board.users" :key="user.uid" class="filter--item">
								<input :id="user.uid"
									v-model="filter.users"
									type="checkbox"
									class="checkbox"
									:value="user.uid"
									@change="setFilter">
								<label :for="user.uid"><NcAvatar :user="user.uid"
									:size="24"
									:disable-menu="true"
									:hide-status="true" /> {{ user.displayname }}</label>
							</div>

							<h3>{{ t('deck', 'Filter by status') }}</h3>
							<div class="filter--item">
								<input id="filter-option-both"
									v-model="filter.completed"
									type="radio"
									class="radio"
									value="both"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="filter-option-both">{{ t('deck', 'Open and completed') }}</label>
							</div>
							<div class="filter--item">
								<input id="filter-option-open"
									v-model="filter.completed"
									type="radio"
									class="radio"
									value="open"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="filter-option-open">{{ t('deck', 'Open') }}</label>
							</div>

							<div class="filter--item">
								<input id="filter-option-completed"
									v-model="filter.completed"
									type="radio"
									class="radio"
									value="completed"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="filter-option-completed">{{ t('deck', 'Completed') }}</label>
							</div>

							<h3>{{ t('deck', 'Filter by due date') }}</h3>
							<div class="filter--item">
								<input id="overdue"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="overdue"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="overdue">{{ t('deck', 'Overdue') }}</label>
							</div>

							<div class="filter--item">
								<input id="dueToday"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="dueToday"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="dueToday">{{ t('deck', 'Next 24 hours') }}</label>
							</div>

							<div class="filter--item">
								<input id="dueWeek"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="dueWeek"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="dueWeek">{{ t('deck', 'Next 7 days') }}</label>
							</div>

							<div class="filter--item">
								<input id="dueMonth"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="dueMonth"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="dueMonth">{{ t('deck', 'Next 30 days') }}</label>
							</div>

							<div class="filter--item">
								<input id="noDue"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="noDue"
									@change="setFilter"
									@click="beforeSetFilter">
								<label for="noDue">{{ t('deck', 'No due date') }}</label>
							</div>

							<div class="filter--save-view">
								<NcTextField v-model="newViewName"
									:label="t('deck', 'View name')"
									:placeholder="t('deck', 'Name for the saved view')"
									@keyup.enter="saveView" />
								<NcButton :disabled="!isFilterActive || newViewName.trim() === ''"
									:wide="true"
									@click="saveView">
									<template #icon>
										<ContentSave :size="20" decorative />
									</template>
									{{ t('deck', 'Save view') }}
								</NcButton>
							</div>

							<NcButton :disabled="!isFilterActive" :wide="true" @click="clearFilter">
								{{ t('deck', 'Clear filter') }}
							</NcButton>
						</div>
					</NcPopover>
				</div>

				<NcActions :aria-label="t('deck', 'View Modes')"
					:name="t('deck', 'Toggle View Modes')">
					<NcActionButton :model-value="viewMode === 'kanban'"
						@click="setViewMode('kanban')">
						<template #icon>
							<ViewColumnIcon :size="20" decorative />
						</template>
						{{ t('deck', 'Kanban view') }}
					</NcActionButton>
					<NcActionButton :model-value="viewMode === 'gantt'"
						@click="setViewMode('gantt')">
						<template #icon>
							<ChartGanttIcon :size="20" decorative />
						</template>
						{{ t('deck', 'Gantt view') }}
					</NcActionButton>
					<NcActionSeparator />
					<NcActionButton @click="() => toggleShowArchived()">
						<template #icon>
							<ArchiveIcon :size="20" decorative />
						</template>
						{{ showArchived ? t('deck', 'Hide archived cards') : t('deck', 'Show archived cards') }}
					</NcActionButton>
					<NcActionButton v-if="compactMode"
						@click="toggleCompactMode">
						<ArrowExpandVerticalIcon slot="icon" :size="20" decorative />
						{{ t('deck', 'Toggle compact mode') }}
					</NcActionButton>
					<NcActionButton v-else
						@click="toggleCompactMode">
						<ArrowCollapseVerticalIcon slot="icon" :size="20" decorative />
						{{ t('deck', 'Toggle compact mode') }}
					</NcActionButton>
					<NcActionButton @click="toggleShowCardCover">
						<template #icon>
							<ImageIcon :size="20" decorative />
						</template>
						{{ showCardCover ? t('deck', 'Hide card cover images') : t('deck', 'Show card cover images') }}
					</NcActionButton>
				</NcActions>
				<!-- FIXME: NcActionRouter currently doesn't work as an inline action -->
				<NcActions v-if="isFullApp">
					<NcActionButton icon="icon-menu-sidebar"
						:aria-label="t('deck', 'Open details')"
						:name="t('deck', 'Details')"
						@click="toggleDetailsView" />
				</NcActions>
			</div>
		</div>
	</div>
</template>

<script>
import { mapState as mapStateVuex } from 'vuex'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcActions, NcActionButton, NcActionSeparator, NcAvatar, NcButton, NcPopover, NcModal, NcTextField } from '@nextcloud/vue'
import labelStyle from '../mixins/labelStyle.js'
import ArchiveIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import ImageIcon from 'vue-material-design-icons/ImageMultipleOutline.vue'
import FilterIcon from 'vue-material-design-icons/FilterOutline.vue'
import FilterOffIcon from 'vue-material-design-icons/FilterOffOutline.vue'
import BookmarkOutline from 'vue-material-design-icons/BookmarkOutline.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import ArrowCollapseVerticalIcon from 'vue-material-design-icons/ArrowCollapseVertical.vue'
import ArrowExpandVerticalIcon from 'vue-material-design-icons/ArrowExpandVertical.vue'
import ViewColumnIcon from 'vue-material-design-icons/ViewColumn.vue'
import ChartGanttIcon from 'vue-material-design-icons/ChartGantt.vue'
import SessionList from './SessionList.vue'
import { isNotifyPushEnabled } from '../sessions.js'
import CreateNewCardCustomPicker from '../views/CreateNewCardCustomPicker.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { mapActions, mapState } from 'pinia'
import { useStackStore } from '../stores/stack.js'
import { useBoardStore } from '../stores/board.js'

export default {
	name: 'Controls',
	components: {
		CreateNewCardCustomPicker,
		NcModal,
		NcActions,
		NcActionButton,
		NcButton,
		NcPopover,
		NcTextField,
		NcAvatar,
		ArchiveIcon,
		ImageIcon,
		FilterIcon,
		FilterOffIcon,
		BookmarkOutline,
		ContentSave,
		TrashCanOutline,
		ArrowCollapseVerticalIcon,
		ArrowExpandVerticalIcon,
		ViewColumnIcon,
		ChartGanttIcon,
		NcActionSeparator,
		TableColumnPlusAfter,
		SessionList,
	},
	mixins: [labelStyle],
	props: {
		board: {
			type: Object,
			required: false,
			default: null,
		},
		overviewName: {
			type: String,
			required: false,
			default: null,
		},
		showSearch: {
			type: Boolean,
			default: false,
		},
		searchLabel: {
			type: String,
			default: '',
		},
		// Only pass this where the card prefixes actually apply
		searchHint: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			newStackTitle: '',
			stack: '',
			filterVisible: false,
			isAddStackVisible: false,
			filter: { tags: [], users: [], due: '', unassigned: false, completed: 'both' },
			newViewName: '',
			showAddCardModal: false,
			defaultPageTitle: false,
			isNotifyPushEnabled: isNotifyPushEnabled(),
		}
	},

	computed: {
		...mapState(useBoardStore, [
			'canEdit',
			'canManage',
			'viewMode',
			'showArchived',
			'boardViews',
		]),
		...mapStateVuex({
			isFullApp: state => state.isFullApp,
			navShown: state => state.navShown,
			compactMode: state => state.compactMode,
			showCardCover: state => state.showCardCover,
			searchQuery: state => state.searchQuery,
		}),
		detailsRoute() {
			return {
				name: 'board.details',
			}
		},
		isFilterActive() {
			return this.filter.tags.length !== 0 || this.filter.users.length !== 0 || this.filter.due !== '' || this.filter.completed !== 'both'
		},
		labelsSorted() {
			return [...this.board.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
		presentUsers() {
			if (!this.board) return []
			// get user object including displayname from the list of all users with acces
			return this.board.users.filter((user) => this.board.activeSessions.includes(user.uid))
		},
	},
	watch: {
		board(current, previous) {
			if (current?.id !== previous?.id) {
				this.clearFilter()
				this.newViewName = ''
				if (current?.id) {
					this.loadBoardViews(current.id)
				}
			}
			if (current) {
				this.setPageTitle(current.title)
			}
		},
	},
	beforeMount() {
		subscribe('deck:board:show-new-card', this.clickShowAddCardModel)
		subscribe('deck:board:toggle-filter-popover', this.triggerOpenFilters)
		subscribe('deck:board:clear-filter', this.triggerClearFilter)
		subscribe('deck:board:toggle-filter-by-me', this.triggerFilterByMe)

	},
	beforeDestroy() {
		unsubscribe('deck:board:show-new-card', this.clickShowAddCardModel)
		unsubscribe('deck:board:toggle-filter-popover', this.triggerOpenFilters)
		unsubscribe('deck:board:clear-filter', this.triggerClearFilter)
		unsubscribe('deck:board:toggle-filter-by-me', this.triggerFilterByMe)
		this.setPageTitle('')
	},
	methods: {
		...mapActions(useBoardStore, {
			setViewMode: 'setViewMode',
			toggleShowArchived: 'toggleShowArchived',
			setFilterInStore: 'setFilterInStore',
			loadBoardViews: 'loadBoardViews',
			createBoardView: 'createBoardView',
			deleteBoardView: 'deleteBoardView',
			applyBoardView: 'applyBoardView',
		}),
		...mapActions(useStackStore, ['createStack']),
		beforeSetFilter(e) {
			if (this.filter.due === e.target.value) {
				this.filter.due = ''
				this.setFilterInStore({ ...this.filter })
			}
			if (e.target.value === 'unassigned') {
				this.filter.users = []
				this.setFilterInStore({ ...this.filter })
			} else {
				this.filter.completed = 'both'
				this.setFilterInStore({ ...this.filter })
			}
			this.setFilterInStore({ ...this.filter })
		},
		setFilter() {
			if (this.filter.users.length > 0) {
				this.filter.unassigned = false
			}
			this.$nextTick(() => this.setFilterInStore({ ...this.filter }))
		},
		setSearchQuery(value) {
			this.$store.commit('setSearchQuery', value)
		},
		clearSearchQuery() {
			this.$store.commit('setSearchQuery', '')
		},
		toggleNav() {
			this.$store.dispatch('toggleNav', !this.navShown)
		},
		toggleCompactMode() {
			this.$store.dispatch('toggleCompactMode')
		},
		toggleShowCardCover() {
			this.$store.dispatch('toggleShowCardCover')
		},
		addNewStack() {
			this.stack = { title: this.newStackTitle }
			this.createStack(this.stack)
			this.newStackTitle = ''
			this.stack = null
			this.isAddStackVisible = false
		},
		showAddStack() {
			this.isAddStackVisible = true
		},
		hideAddStack() {
			this.isAddStackVisible = false
		},
		toggleDetailsView() {
			if (this.$route.name === 'board.details') {
				this.$router.push({ name: 'board' })
			} else {
				this.$router.push({ name: 'board.details' })
			}
		},
		clearFilter() {
			const filterReset = { tags: [], users: [], due: '', unassigned: false, completed: 'both' }
			this.setFilterInStore({ ...filterReset })
			this.filter = filterReset
		},
		applyView(view) {
			this.applyBoardView(view)
			this.filter = {
				tags: [...(view.filters?.tags || [])],
				users: [...(view.filters?.users || [])],
				due: view.filters?.due || '',
				unassigned: view.filters?.unassigned || false,
				completed: view.filters?.completed || 'both',
			}
		},
		async removeView(view) {
			await this.deleteBoardView(view.boardId, view.id)
		},
		async saveView() {
			if (!this.isFilterActive || this.newViewName.trim() === '' || !this.board) {
				return
			}
			await this.createBoardView(this.board.id, this.newViewName.trim())
			this.newViewName = ''
		},
		clickShowAddCardModel() {
			this.showAddCardModal = true
		},
		clickHideAddCardModel() {
			this.showAddCardModal = false
		},
		setPageTitle(title) {
			if (!this.isFullApp) {
				return
			}
			if (this.defaultPageTitle === false) {
				this.defaultPageTitle = window.document.title
				if (this.defaultPageTitle.indexOf(' - Deck - ') !== -1) {
					this.defaultPageTitle = this.defaultPageTitle.substring(this.defaultPageTitle.indexOf(' - Deck - ') + 3)
				}
				if (this.defaultPageTitle.indexOf('Deck - ') !== 0) {
					this.defaultPageTitle = 'Deck - ' + this.defaultPageTitle
				}
			}
			let newTitle = this.defaultPageTitle
			if (title !== '') {
				newTitle = `${title} - ${newTitle}`
			}
			window.document.title = newTitle
		},
		triggerOpenFilters() {
			this.$refs.filterPopover.$el.click()
		},
		triggerClearFilter() {
			this.clearFilter()
		},
		triggerFilterByMe() {
			if (this.isFilterActive) {
				this.clearFilter()
			} else {
				this.filter.users = [getCurrentUser().uid]
				this.setFilter()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
	@import '../css/variables.scss';

	.controls {
		display: flex;
		// min-height, not height: the search wraps to a second row on narrow screens
		flex-wrap: wrap;
		row-gap: var(--default-grid-baseline);
		margin: calc(var(--default-grid-baseline) * 2);
		min-height: var(--default-clickable-area);
		padding-inline-start: var(--default-clickable-area);

		.board-title {
			display: flex;
			align-items: center;
			// lets the h2 below actually truncate
			min-width: 0;

			h2 {
				margin: 0;
				margin-inline-end: 10px;
				font-size: 18px;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			.board-bullet {
				display: inline-block;
				width: 16px;
				height: 16px;
				border: none;
				border-radius: 50%;
				background-color: transparent;
				margin: var(--default-grid-baseline);
			}
		}

		#stack-add form {
			display: flex;

			#new-stack-input-main {
				margin-inline-end: 8px;
			}
			.icon-confirm {
				border: 2px solid var(--color-border-maxcontrast) !important;
				border-inline-start: none !important;
			}
			&:focus-within, &:focus, &:focus-visible,
			&:hover {
				.icon-confirm {
					border-color: var(--color-main-text) !important;
				}
			}
		}
	}

	#app-navigation-toggle-custom {
		position: static;
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		cursor: pointer;
		opacity: 1;
		display: inline-block !important;
	}

	.board-actions {
		flex-grow: 1;
		order: 100;
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		row-gap: var(--default-grid-baseline);
		justify-content: flex-end;
	}

	.board-action-buttons {
		display: flex;
	}

	.board-search {
		flex: 0 1 15rem;
		min-width: 0;
		margin-inline-end: var(--default-grid-baseline);
	}

	@media (max-width: $breakpoint-small-mobile) {
		// Own row below the buttons, spanning the full header. The negative margin cancels
		// the padding .controls reserves for the navigation toggle, which only occupies the
		// first row; the oversized basis keeps the search alone on its line, so it is safe.
		.board-search {
			order: 1;
			flex-basis: calc(100% + var(--default-clickable-area));
			margin-inline: calc(-1 * var(--default-clickable-area)) 0;
		}
	}

	.filter--item {
		input + label {
			display: block;
			padding: var(--default-grid-baseline) 0;
			.avatardiv {
				vertical-align: middle;
				margin-bottom: 2px;
				margin-inline-end: 3px;
			}
			.label {
				padding: 5px;
				border-radius: 3px;
			}
		}
	}

	.filter {
		width: 240px;
		max-height: calc(100vh - 150px);
		position: relative;
		overflow: auto;
		padding: 8px;
	}

	.filter h3 {
		margin-top: 0px;
		margin-bottom: 5px;
	}

	.filter--saved-views {
		border-bottom: 1px solid var(--color-border);
		margin-bottom: 8px;
		padding-bottom: 4px;

		.filter--saved-view {
			display: flex;
			align-items: center;
			gap: 4px;

			.filter--saved-view-name {
				flex-grow: 1;
				justify-content: flex-start;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		}
	}

	.filter--save-view {
		margin-top: 8px;
	}

	.filter-button {
		padding: 0;
		border-radius: 50%;
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);

		&[data-popper-shown] {
			background-color: var(--color-background-hover);
			&.button-vue--vue-primary {
				background-color: var(--color-primary-element);
			}
		}
	}
</style>
<style lang="scss">
	.popover:focus {
		outline: 2px solid var(--color-main-text);
	}
</style>
