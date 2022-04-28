<!--
* @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
*
* @author Michael Weimann <mail@michael-weimann.eu>
*
* @license GNU AGPL version 3 or any later version
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
-->

<template>
	<div class="controls">
		<div v-if="overviewName" class="board-title">
			<div class="board-bullet icon-calendar-dark" />
			<h2>{{ overviewName }}</h2>
			<Actions>
				<ActionButton icon="icon-add" @click="clickShowAddCardModel">
					{{ t('deck', 'Add card') }}
				</ActionButton>
			</Actions>
			<CardCreateDialog v-if="showAddCardModal" @close="clickHideAddCardModel" />
		</div>
		<div v-else-if="board" class="board-title">
			<div :style="{backgroundColor: '#' + board.color}" class="board-bullet" />
			<h2>{{ board.title }}</h2>
			<p v-if="showArchived">
				({{ t('deck', 'Archived cards') }})
			</p>
		</div>
		<div class="board-actions">
			<div v-if="searchQuery || true" class="deck-search">
				<input type="search"
					class="icon-search"
					:value="searchQuery"
					@input="$store.commit('setSearchQuery', $event.target.value)">
			</div>
			<div v-if="board && canManage && !showArchived && !board.archived"
				id="stack-add"
				v-click-outside="hideAddStack">
				<Actions v-if="!isAddStackVisible">
					<ActionButton icon="icon-add" @click.stop="showAddStack">
						{{ t('deck', 'Add list') }}
					</ActionButton>
				</Actions>
				<form v-else @submit.prevent="addNewStack()">
					<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add list') }}</label>
					<input id="new-stack-input-main"
						v-model="newStackTitle"
						v-focus
						type="text"
						class="no-close"
						:placeholder="t('deck', 'List name')"
						required>
					<input v-tooltip="t('deck', 'Add list')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</div>
			<div v-if="board" class="board-action-buttons">
				<Popover @show="filterVisible=true" @hide="filterVisible=false">
					<Actions slot="trigger" :title="t('deck', 'Apply filter')">
						<ActionButton v-if="isFilterActive" icon="icon-filter_set" />
						<ActionButton v-else icon="icon-filter" />
					</Actions>

					<div v-if="filterVisible" class="filter">
						<h3>{{ t('deck', 'Filter by tag') }}</h3>
						<div v-for="label in labelsSorted" :key="label.id" class="filter--item">
							<input
								:id="label.id"
								v-model="filter.tags"
								type="checkbox"
								class="checkbox"
								:value="label.id"
								@change="setFilter">
							<label :for="label.id"><span class="label" :style="labelStyle(label)">{{ label.title }}</span></label>
						</div>

						<h3>{{ t('deck', 'Filter by assigned user') }}</h3>
						<div class="filter--item">
							<input
								id="unassigned"
								v-model="filter.unassigned"
								type="checkbox"
								class="checkbox"
								value="unassigned"
								@change="setFilter"
								@click="beforeSetFilter">
							<label for="unassigned">{{ t('deck', 'Unassigned') }}</label>
						</div>
						<div v-for="user in board.users" :key="user.uid" class="filter--item">
							<input
								:id="user.uid"
								v-model="filter.users"
								type="checkbox"
								class="checkbox"
								:value="user.uid"
								@change="setFilter">
							<label :for="user.uid"><Avatar :user="user.uid" :size="24" :disable-menu="true" /> {{ user.displayname }}</label>
						</div>

						<h3>{{ t('deck', 'Filter by due date') }}</h3>

						<div class="filter--item">
							<input
								id="overdue"
								v-model="filter.due"
								type="radio"
								class="radio"
								value="overdue"
								@change="setFilter"
								@click="beforeSetFilter">
							<label for="overdue">{{ t('deck', 'Overdue') }}</label>
						</div>

						<div class="filter--item">
							<input
								id="dueToday"
								v-model="filter.due"
								type="radio"
								class="radio"
								value="dueToday"
								@change="setFilter"
								@click="beforeSetFilter">
							<label for="dueToday">{{ t('deck', 'Next 24 hours') }}</label>
						</div>

						<div class="filter--item">
							<input
								id="dueWeek"
								v-model="filter.due"
								type="radio"
								class="radio"
								value="dueWeek"
								@change="setFilter"
								@click="beforeSetFilter">
							<label for="dueWeek">{{ t('deck', 'Next 7 days') }}</label>
						</div>

						<div class="filter--item">
							<input
								id="dueMonth"
								v-model="filter.due"
								type="radio"
								class="radio"
								value="dueMonth"
								@change="setFilter"
								@click="beforeSetFilter">
							<label for="dueMonth">{{ t('deck', 'Next 30 days') }}</label>
						</div>

						<div class="filter--item">
							<input
								id="noDue"
								v-model="filter.due"
								type="radio"
								class="radio"
								value="noDue"
								@change="setFilter"
								@click="beforeSetFilter">
							<label for="noDue">{{ t('deck', 'No due date') }}</label>
						</div>

						<Button :disabled="!isFilterActive" @click="clearFilter">
							{{ t('deck', 'Clear filter') }}
						</Button>
					</div>
				</Popover>

				<Actions>
					<ActionButton
						icon="icon-archive"
						@click="toggleShowArchived">
						{{ showArchived ? t('deck', 'Hide archived cards') : t('deck', 'Show archived cards') }}
					</ActionButton>
					<ActionButton v-if="compactMode"
						icon="icon-toggle-compact-collapsed"
						@click="toggleCompactMode">
						{{ t('deck', 'Toggle compact mode') }}
					</ActionButton>
					<ActionButton v-else
						icon="icon-toggle-compact-expanded"
						@click="toggleCompactMode">
						{{ t('deck', 'Toggle compact mode') }}
					</ActionButton>
				</Actions>
				<!-- FIXME: ActionRouter currently doesn't work as an inline action -->
				<Actions :title="t('deck', 'Details')">
					<ActionButton icon="icon-menu-sidebar" @click="toggleDetailsView" />
				</Actions>
			</div>
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import { Actions, ActionButton, Popover, Avatar } from '@nextcloud/vue'
import labelStyle from '../mixins/labelStyle'
import CardCreateDialog from '../CardCreateDialog'

export default {
	name: 'Controls',
	components: {
		Actions, ActionButton, Popover, Avatar, CardCreateDialog,
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
			filter: { tags: [], users: [], due: '', unassigned: false },
			showAddCardModal: false,
			defaultPageTitle: false,
		}
	},

	computed: {
		...mapGetters([
			'canEdit',
			'canManage',
		]),
		...mapState({
			compactMode: state => state.compactMode,
			searchQuery: state => state.searchQuery,
		}),
		detailsRoute() {
			return {
				name: 'board.details',
			}
		},
		isFilterActive() {
			if (this.filter.tags.length !== 0 || this.filter.users.length !== 0 || this.filter.due !== '') {
				return true
			}
			return false
		},
		labelsSorted() {
			return [...this.board.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
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
	beforeDestroy() {
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
			}
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
			const filterReset = { tags: [], users: [], due: '' }
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
	},
}
</script>

<style lang="scss" scoped>
	.controls {
		display: flex;
		padding: 3px;
		height: 44px;
		padding-left: 44px;

		.board-title {
			display: flex;
			align-items: center;

			h2 {
				margin: 0;
				margin-right: 10px;
			}

			.board-bullet {
				display: inline-block;
				width: 20px;
				height: 20px;
				border: none;
				border-radius: 50%;
				background-color: transparent;
				margin: 12px;
				margin-left: -4px;
			}
		}

		#stack-add form {
			display: flex;
		}
	}

	#app-navigation-toggle-custom {
		position: static;
		width: 44px;
		height: 44px;
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
		button {
			border: 0;
			width: 44px;
			margin: 0 0 0 -1px;
			background-color: transparent;
		}
	}

	.deck-search {
		input[type=search] {
			background-position: 5px;
			padding-left: 24px;
		}
	}

	.filter--item {
		input + label {
			display: block;
			padding: 6px 0;
			vertical-align: middle;
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
		width: 250px;
		max-height: 80vh;
		overflow: auto;
		padding: 8px;
	}

	.filter h3 {
		margin-top: 0px;
		margin-bottom: 5px;
	}
</style>
<style lang="scss">
	.tooltip-inner.popover-inner {
		text-align: left;
	}
</style>
