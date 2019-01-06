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

	<div id="content" :class="{ 'nav-hidden': !navShown, 'sidebar-hidden': !sidebarRouterView }">
		<AppNavigation />
		<div id="app-content">
			<router-view />
		</div>
		<router-view name="sidebar" />
	</div>

</template>

<script>

import { mapState } from 'vuex'
import BoardSidebar from './components/board/BoardSidebar'
import AppNavigation from './components/navigation/AppNavigation';

export default {
	name: 'App',
	components: {
		AppNavigation,
		BoardSidebar
	},
	data: function() {
		return {
			addButton: {
				icon: 'icon-add',
				classes: [],
				text: t('deck', 'Create new board'),
				edit: {
					text: t('deck', 'new board'),
					action: () => {
					},
					reset: () => {
					}
				},
				action: () => {
					this.addButton.classes.push('editing')
				}
			}
		}
	},
	computed: {
		...mapState({
			navShown: state => state.navShown,
			sidebarShownState: state => state.sidebarShown,
			currentBoard: state => state.currentBoard
		}),
		// TODO: properly handle sidebar showing for route subview and board sidebar
		sidebarRouterView() {
			console.log(this.$route)
			return this.$route.name === 'card' || this.$route.name === 'board.details'
		},
		sidebarShown() {
			return this.sidebarRouterView || this.sidebarShownState
		}
	},
	created: function() {
		this.$store.dispatch('loadBoards')
		this.$store.dispatch('loadSharees')
	}
}

</script>

<style lang="scss" scoped>

	#content {
		#app-content {
			transition: margin-left 100ms ease;
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

	.deck-main {
		bottom: 0;
		overflow: auto;
		position: absolute;
		top: 44px;
		width: 100%;
	}

</style>
