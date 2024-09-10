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
			<!-- Hide but not remove for now as search might change in the future -->
			<div v-if="false" class="deck-search">
				<input id="deck-search-input"
					ref="search"
					:tabindex="0"
					type="search"
					class="icon-search"
					:value="searchQuery"
					@focus="$store.dispatch('toggleShortcutLock', true)"
					@blur="$store.dispatch('toggleShortcutLock', false)"
					@input="$store.commit('setSearchQuery', $event.target.value)">
			</div>
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
								<label :for="user.uid"><NcAvatar :user="user.uid" :size="24" :disable-menu="true" /> {{ user.displayname }}</label>
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

							<NcButton :disabled="!isFilterActive" :wide="true" @click="clearFilter">
								{{ t('deck', 'Clear filter') }}
							</NcButton>
						</div>
					</NcPopover>
				</div>

				<NcActions :aria-label="t('deck', 'View Modes')"
					:name="t('deck', 'Toggle View Modes')">
					<NcActionButton @click="toggleShowArchived">
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
import { mapState, mapGetters } from 'vuex'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { NcActions, NcActionButton, NcAvatar, NcButton, NcPopover, NcModal } from '@nextcloud/vue'
import labelStyle from '../mixins/labelStyle.js'
import ArchiveIcon from 'vue-material-design-icons/Archive.vue'
import ImageIcon from 'vue-material-design-icons/ImageMultiple.vue'
import FilterIcon from 'vue-material-design-icons/Filter.vue'
import FilterOffIcon from 'vue-material-design-icons/FilterOff.vue'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import ArrowCollapseVerticalIcon from 'vue-material-design-icons/ArrowCollapseVertical.vue'
import ArrowExpandVerticalIcon from 'vue-material-design-icons/ArrowExpandVertical.vue'
import SessionList from './SessionList.vue'
import { isNotifyPushEnabled } from '../sessions.js'
import CreateNewCardCustomPicker from '../views/CreateNewCardCustomPicker.vue'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'Controls',
	components: {
		CreateNewCardCustomPicker,
		NcModal,
		NcActions,
		NcActionButton,
		NcButton,
		NcPopover,
		NcAvatar,
		ArchiveIcon,
		ImageIcon,
		FilterIcon,
		FilterOffIcon,
		ArrowCollapseVerticalIcon,
		ArrowExpandVerticalIcon,
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
	},
	data() {
		return {
			newStackTitle: '',
			stack: '',
			filterVisible: false,
			showArchived: false,
			isAddStackVisible: false,
			filter: { tags: [], users: [], due: '', unassigned: false, completed: 'both' },
			showAddCardModal: false,
			defaultPageTitle: false,
			isNotifyPushEnabled: isNotifyPushEnabled(),
		}
	},

	computed: {
		...mapGetters([
			'canEdit',
			'canManage',
		]),
		...mapState({
			isFullApp: state => state.isFullApp,
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
		beforeSetFilter(e) {
			if (this.filter.due === e.target.value) {
				this.filter.due = ''
				this.$store.dispatch('setFilter', { ...this.filter })
			}
			if (e.target.value === 'unassigned') {
				this.filter.users = []
				this.$store.dispatch('setFilter', { ...this.filter })
			} else {
				this.filter.completed = 'both'
				this.$store.dispatch('setFilter', { ...this.filter })
			}
			this.$store.dispatch('setFilter', { ...this.filter })
		},
		setFilter() {
			if (this.filter.users.length > 0) {
				this.filter.unassigned = false
			}
			this.$nextTick(() => this.$store.dispatch('setFilter', { ...this.filter }))
		},
		toggleNav() {
			this.$store.dispatch('toggleNav')
		},
		toggleCompactMode() {
			this.$store.dispatch('toggleCompactMode')
		},
		toggleShowCardCover() {
			this.$store.dispatch('toggleShowCardCover')
		},
		toggleShowArchived() {
			this.$store.dispatch('toggleShowArchived')
			this.showArchived = !this.showArchived
		},
		addNewStack() {
			this.stack = { title: this.newStackTitle }
			this.$store.dispatch('createStack', this.stack)
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
			const filterReset = { tags: [], users: [], due: '', completed: 'both' }
			this.$store.dispatch('setFilter', { ...filterReset })
			this.filter = filterReset
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
		triggerOpenSearch() {
			this.$refs.search.focus()
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
	.controls {
		display: flex;
		margin: calc(var(--default-grid-baseline) * 2);
		height: var(--default-clickable-area);
		padding-left: var(--default-clickable-area);

		.board-title {
			display: flex;
			align-items: center;

			h2 {
				margin: 0;
				margin-right: 10px;
				font-size: 18px;
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
		justify-content: flex-end;
	}

	.board-action-buttons {
		display: flex;
	}

	.deck-search {
		display: flex;
		align-items: center;
		justify-content: center;
		input[type=search] {
			background-position: 5px;
			padding-left: 24px !important;
		}
	}

	.filter--item {
		input + label {
			display: block;
			padding: var(--default-grid-baseline) 0;
			.avatardiv {
				vertical-align: middle;
				margin-bottom: 2px;
				margin-right: 3px;
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
