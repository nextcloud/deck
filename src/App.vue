<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="deck" :class="{ 'nav-hidden': !navShown, 'sidebar-hidden': !sidebarRouterView }">
		<AppNavigation />
		<NcAppContent :allow-swipe-navigation="false">
			<router-view />
		</NcAppContent>

		<div v-if="$route.params.id || $route.params.cardId">
			<NcModal v-if="cardDetailsInModal && $route.params.cardId"
				:clear-view-delay="0"
				:close-button-contained="true"
				size="large"
				@close="hideModal()">
				<div class="modal__content modal__card">
					<router-view name="sidebar" />
				</div>
			</NcModal>

			<router-view name="sidebar" :visible="!cardDetailsInModal || !$route.params.cardId" />
		</div>
		<KeyboardShortcuts />
		<CardMoveDialog />
	</NcContent>
</template>

<script>
import { mapState } from 'vuex'
import AppNavigation from './components/navigation/AppNavigation.vue'
import KeyboardShortcuts from './components/KeyboardShortcuts.vue'
import { NcModal, NcContent, NcAppContent } from '@nextcloud/vue'
import { BoardApi } from './services/BoardApi.js'
import { emit, subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import CardMoveDialog from './CardMoveDialog.vue'

const boardApi = new BoardApi()

export default {
	name: 'App',
	components: {
		CardMoveDialog,
		AppNavigation,
		NcModal,
		NcContent,
		NcAppContent,
		KeyboardShortcuts,
	},
	provide() {
		return {
			boardApi,
		}
	},
	data() {
		return {
			addButton: {
				icon: 'icon-add',
				classes: [],
				text: t('deck', 'Add board'),
				edit: {
					text: t('deck', 'Add board'),
					action: () => {
					},
					reset: () => {
					},
				},
				action: () => {
					this.addButton.classes.push('editing')
				},
			},
		}
	},
	computed: {
		...mapState({
			navShown: state => state.navShown,
			sidebarShownState: state => state.sidebarShown,
			currentBoard: state => state.currentBoard,
		}),
		// TODO: properly handle sidebar showing for route subview and board sidebar
		sidebarRouterView() {
			// console.log(this.$route)
			return this.$route.name === 'card' || this.$route.name === 'board.details'
		},
		sidebarShown() {
			return this.sidebarRouterView || this.sidebarShownState
		},
		cardDetailsInModal: {
			get() {
				return this.$store.getters.config('cardDetailsInModal')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { cardDetailsInModal: newValue })
			},
		},
	},
	created() {
		const initialState = loadState('deck', 'initialBoards', null)
		if (initialState !== null) {
			this.$store.dispatch('loadBoards')
		}
		this.$store.dispatch('loadSharees')
	},
	mounted() {
		// Set navigation to initial state and update in case it gets toggled
		emit('toggle-navigation', { open: this.navShown, _initial: true })
		this.$nextTick(() => {
			subscribe('navigation-toggled', (navState) => {
				this.$store.dispatch('toggleNav', navState.open)
			})
		})
	},
	methods: {
		hideModal() {
			this.$router.push({ name: 'board' })
		},
	},
}
</script>

<style lang="scss" scoped>

	#content-vue {
		#app-content {
			transition: margin-left 100ms ease;
			position: relative;
			overflow-x: hidden;
			align-items: stretch;
		}

		#app-sidebar {
			transition: max-width 100ms ease;
		}

		&.nav-hidden {
			#app-content {
				margin-left: 0;
			}
		}

		&.sidebar-hidden {
			#app-sidebar {
				max-width: 0;
				min-width: 0;
			}
		}
	}
</style>

<style lang="scss">
	@import '../css/print';

	.icon-activity {
		background-image: url(../img/activity-dark.svg);

		body[data-theme-dark] & {
			background-image: url(../img/activity.svg);
		}
	}

	.avatardiv.circles {
		background: var(--color-primary-element);
	}

	.icon-circles {
		background-image: url(../img/circles-dark.svg);
		opacity: 1;
		background-size: 20px;
		background-position: center center;
	}

	.icon-circles-white, .icon-circles.icon-white {
		background-image: url(../img/circles.svg);
		opacity: 1;
		background-size: 20px;
		background-position: center center;
	}

	.icon-colorpicker {
		background-image: url('../img/color_picker.svg');
	}

	.v-select {
		width: 100%;
	}

	.modal__card {
		width: 100%;
		min-width: 100%;
		height: calc(100% - 20px);
		overflow: hidden;
	}

</style>
