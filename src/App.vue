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

<div
		id="content"
		v-bind:class="{ 'nav-hidden': navHidden, 'sidebar-hidden': sidebarHidden }">
	<AppNavigation :menu="menu" />
	<div id="app-content">
		<Controls></Controls>
		<router-view />
	</div>
	<div id="app-sidebar">
        <component v-bind:is="sidebarComponent"></component>
	</div>
</div>

</template>

<script>

import { AppNavigation } from 'nextcloud-vue'
import Controls from './components/Controls'
import { mapState } from 'vuex'
import Sidebar from './components/Sidebar'

export default {
	name: 'App',
	components: {
		AppNavigation,
		Controls,
        Sidebar
	},
	computed: mapState({
		navHidden: state => state.nav.hidden,
		sidebarHidden: state => state.sidebar.hidden,
		menu: state => state.nav.menu,
        sidebarComponent: state => state.sidebar.component
	})
}

</script>

<style lang="scss" scoped>

#content {
	#app-content {
		transition: margin-left 100ms ease;
	}

	#app-sidebar {
		transition: width 100ms ease;
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
