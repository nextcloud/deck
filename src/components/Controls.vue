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
		{{ filter }}
		<div id="app-navigation-toggle-custom" class="icon-menu" @click="toggleNav" />
		<div v-if="board" class="board-title">
			<div :style="{backgroundColor: '#' + board.color}" class="board-bullet" />
			<h2><a href="#">{{ board.title }}</a></h2>
		</div>
		<div v-if="board" class="board-actions">
			<div v-if="canManage" id="stack-add" v-click-outside="hideAddStack">
				<Actions v-if="!isAddStackVisible">
					<ActionButton icon="icon-add" :title="t('deck', 'Add new list')" @click.stop="showAddStack" />
				</Actions>
				<form v-else @submit.prevent="addNewStack()">
					<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add new list') }}</label>
					<input id="new-stack-input-main"
						v-model="newStackTitle"
						type="text"
						class="no-close"
						:placeholder="t('deck', 'List name')"
						required>
					<input v-tooltip="t('deck', 'Add new list')"
						class="icon-confirm"
						type="submit"
						value="">
				</form>
			</div>
			<div class="board-action-buttons">
				<Popover>
					<Actions slot="trigger">
						<ActionButton icon="icon-filter" :title="t('deck', 'Apply filter')" />
					</Actions>

					<template>
						<div class="filter">
							<h3>{{ t('deck', 'Filter by tag') }}</h3>
							<div v-for="label in board.labels" :key="label.id" class="filter--item">
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

							<h3>{{ t('deck', 'Filter by duedate') }}</h3>

							<div class="filter--item">
								<input
									id="overdue"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="overdue"
									@change="setFilter">
								<label for="overdue">{{ t('deck', 'Overdue') }}</label>
							</div>

							<div class="filter--item">
								<input
									id="dueToday"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="dueToday"
									@change="setFilter">
								<label for="dueToday">{{ t('deck', 'Today') }}</label>
							</div>

							<div class="filter--item">
								<input
									id="dueWeek"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="dueWeek"
									@change="setFilter">
								<label for="dueWeek">{{ t('deck', 'Week') }}</label>
							</div>

							<div class="filter--item">
								<input
									id="dueMonth"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="dueMonth"
									@change="setFilter">
								<label for="dueMonth">{{ t('deck', 'Month') }}</label>
							</div>

							<div class="filter--item">
								<input
									id="noDue"
									v-model="filter.due"
									type="radio"
									class="radio"
									value="noDue"
									@change="setFilter">
								<label for="noDue">{{ t('deck', 'No due') }}</label>
							</div>
						</div>
					</template>
				</Popover>

				<Actions style="opacity: .5;">
					<ActionButton v-if="showArchived"
						icon="icon-archive"
						:title="t('deck', 'Show archived cards')"
						@click="toggleShowArchived" />
					<ActionButton v-else
						icon="icon-archive"
						:title="t('deck', 'Hide archived cards')"
						@click="toggleShowArchived" />
				</Actions>
				<Actions>
					<ActionButton v-if="compactMode"
						icon="icon-toggle-compact-collapsed"
						:title="t('deck', 'Toggle compact mode')"
						@click="toggleCompactMode" />
					<ActionButton v-else
						icon="icon-toggle-compact-expanded"
						:title="t('deck', 'Toggle compact mode')"
						@click="toggleCompactMode" />
				</Actions>
				<!-- FIXME: ActionRouter currently doesn't work as an inline action -->
				<Actions>
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

export default {
	name: 'Controls',
	components: {
		Actions, ActionButton, Popover, Avatar,
	},
	mixins: [ labelStyle ],
	props: {
		board: {
			type: Object,
			required: false,
			default: null,
		},
	},
	data() {
		return {
			newStackTitle: '',
			stack: '',
			showArchived: false,
			isAddStackVisible: false,
			filter: { tags: [], users: [], due: '' },
		}
	},

	computed: {
		...mapGetters([
			'canEdit',
			'canManage',
		]),
		...mapState({
			compactMode: state => state.compactMode,
		}),
		detailsRoute() {
			return {
				name: 'board.details',
			}
		},
	},
	methods: {
		setFilter() {
			this.$nextTick(() => this.$store.dispatch('setFilter', this.filter))
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
	},
}
</script>

<style lang="scss" scoped>
	.controls {
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
				background-color: #aaa;
				margin: 12px;
				margin-left: -4px;
			}
		}

		#stack-add form {
			display: flex;
		}
	}

	#app-navigation-toggle-custom {
		width: 44px;
		height: 44px;
		cursor: pointer;
		opacity: 1;
		display: inline-block !important;
		position: fixed;
	}

	.controls {
		display: flex;
	}

	#app-navigation-toggle-custom {
		position: static;
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
