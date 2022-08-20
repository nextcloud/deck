<!--
 - @copyright Copyright (c) 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 -
 - @author Christoph Wurst <christoph@winzerhof-wurst.at>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<NcContent id="content" app-name="deck" :class="{ 'nav-hidden': !navShown, 'sidebar-hidden': !sidebarRouterView }">
		<AppNavigation />
		<NcAppContent>
			<router-view />
		</NcAppContent>

		<NcModal v-if="cardDetailsInModal && $route.params.cardId"
			:clear-view-delay="0"
			:title="t('deck', 'Card details')"
			size="large"
			@close="hideModal()">
			<div class="modal__content modal__card">
				<router-view name="sidebar" />
			</div>
		</NcModal>

		<router-view name="sidebar" :visible="!cardDetailsInModal || !$route.params.cardId" />
	</NcContent>
</template>

<script>
import { mapState } from 'vuex'
import AppNavigation from './components/navigation/AppNavigation'
import { NcModal, NcContent, NcAppContent } from '@nextcloud/vue'
import { BoardApi } from './services/BoardApi'
import { emit, subscribe } from '@nextcloud/event-bus'

const boardApi = new BoardApi()

export default {
	name: 'App',
	components: {
		AppNavigation,
		NcModal,
		NcContent,
		NcAppContent,
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
		this.$store.dispatch('loadBoards')
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

	#content {
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
	@import "../css/print";

	.icon-activity {
		background-image: url(../img/activity-dark.svg);

		body[data-theme-dark] & {
			background-image: url(../img/activity.svg);
		}
	}

	.avatardiv.circles {
		background: var(--color-primary);
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

	.multiselect {
		width: 100%;
	}

</style>
