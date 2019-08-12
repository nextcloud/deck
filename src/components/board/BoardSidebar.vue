<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
	<app-sidebar v-if="board != null"
		:actions="[]"
		:title="board.title"
		@close="closeSidebar">

		<AppSidebarTab name="Sharing" icon="icon-shared">
			<SharingTabSidebard :board="board" />
		</AppSidebarTab>

		<AppSidebarTab name="Tags" icon="icon-tag">
			<TagsTabSidebard :board="board" />
		</AppSidebarTab>

		<AppSidebarTab name="Deleted items" icon="icon-delete">
			<DeletedTabSidebar :board="board" />
		</AppSidebarTab>

		<AppSidebarTab name="Timeline" icon="icon-activity">
			<TimelineTabSidebard :board="board" />
		</AppSidebarTab>

	</app-sidebar>
</template>

<script>
import { mapState } from 'vuex'
import SharingTabSidebard from './SharingTabSidebard'
import TagsTabSidebard from './TagsTabSidebard'
import DeletedTabSidebar from './DeletedTabSidebar'
import TimelineTabSidebard from './TimelineTabSidebard'
import { AppSidebar, AppSidebarTab } from 'nextcloud-vue'

export default {
	name: 'BoardSidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		SharingTabSidebard,
		TagsTabSidebard,
		DeletedTabSidebar,
		TimelineTabSidebard
	},
	props: {
		id: {
			type: Number
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
			labels: state => state.labels
		})
	},
	methods: {
		closeSidebar() {
			this.$router.push({ name: 'board' })
		}
	}
}
</script>
