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
	<NcAppSidebar v-if="board != null"
		:actions="[]"
		:title="board.title"
		@close="closeSidebar">
		<NcAppSidebarTab id="sharing"
			:order="0"
			:name="t('deck', 'Sharing')"
			icon="icon-shared">
			<SharingTabSidebar :board="board" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="tags"
			:order="1"
			:name="t('deck', 'Tags')"
			icon="icon-tag">
			<TagsTabSidebar :board="board" />
		</NcAppSidebarTab>

		<NcAppSidebarTab v-if="canEdit"
			id="deleted"
			:order="2"
			:name="t('deck', 'Deleted items')"
			icon="icon-delete">
			<DeletedTabSidebar :board="board" />
		</NcAppSidebarTab>

		<NcAppSidebarTab v-if="hasActivity"
			id="activity"
			:order="3"
			:name="t('deck', 'Timeline')"
			icon="icon-activity">
			<TimelineTabSidebar :board="board" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import SharingTabSidebar from './SharingTabSidebar'
import TagsTabSidebar from './TagsTabSidebar'
import DeletedTabSidebar from './DeletedTabSidebar'
import TimelineTabSidebar from './TimelineTabSidebar'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'

const capabilities = window.OC.getCapabilities()

export default {
	name: 'BoardSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		SharingTabSidebar,
		TagsTabSidebar,
		DeletedTabSidebar,
		TimelineTabSidebar,
	},
	props: {
		id: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			hasActivity: capabilities && capabilities.activity,
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
			labels: state => state.labels,
		}),
		...mapGetters(['canEdit']),
	},
	methods: {
		closeSidebar() {
			this.$router.push({ name: 'board' })
		},
	},
}
</script>
