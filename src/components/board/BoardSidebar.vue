<!--
	- SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar v-if="board != null"
		:actions="[]"
		:name="board.title"
		@close="closeSidebar">
		<NcAppSidebarTab id="sharing"
			:order="0"
			:name="t('deck', 'Sharing')">
			<template #icon>
				<SharingIcon :size="20" />
			</template>
			<SharingTabSidebar :board="board" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="tags"
			:order="1"
			:name="t('deck', 'Tags')">
			<template #icon>
				<TagsIcon :size="20" />
			</template>
			<TagsTabSidebar :board="board" />
		</NcAppSidebarTab>

		<NcAppSidebarTab v-if="canEdit"
			id="deleted"
			:order="2"
			:name="t('deck', 'Deleted items')">
			<template #icon>
				<TrashIcon :size="20" />
			</template>
			<DeletedTabSidebar :board="board" />
		</NcAppSidebarTab>

		<NcAppSidebarTab v-if="hasActivity"
			id="activity"
			:order="3"
			:name="t('deck', 'Activity')">
			<template #icon>
				<ActivityIcon :size="20" />
			</template>
			<TimelineTabSidebar :board="board" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import SharingTabSidebar from './SharingTabSidebar.vue'
import TagsTabSidebar from './TagsTabSidebar.vue'
import DeletedTabSidebar from './DeletedTabSidebar.vue'
import TimelineTabSidebar from './TimelineTabSidebar.vue'
import { NcAppSidebar, NcAppSidebarTab } from '@nextcloud/vue'
import ActivityIcon from 'vue-material-design-icons/LightningBolt.vue'
import SharingIcon from 'vue-material-design-icons/ShareVariant.vue'
import TagsIcon from 'vue-material-design-icons/TagMultiple.vue'
import TrashIcon from 'vue-material-design-icons/Delete.vue'
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
		ActivityIcon,
		SharingIcon,
		TagsIcon,
		TrashIcon,
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

<style scoped lang="scss">
:deep {
	.app-sidebar-tabs__tab-caption,
	.app-sidebar-tabs__nav .checkbox-content__text {
		white-space: normal !important;
	}
}
</style>
