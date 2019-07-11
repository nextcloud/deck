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
	<app-sidebar v-if="currentCard != null"
		:actions="[]"
		:title="currentCard.title"
		:subtitle="subtitle"
		@close="closeSidebar">
		<template #action />
		<AppSidebarTab name="Description" icon="icon-description">
			{{ currentCard.description }}
		</AppSidebarTab>
		<AppSidebarTab name="Attachments" icon="icon-files-dark">
			{{ currentCard.attachments }}
		</AppSidebarTab>
		<AppSidebarTab name="Timeline" icon="icon-activity">
			this is the activity tab
		</AppSidebarTab>
	</app-sidebar>
</template>

<script>
import { AppSidebar, AppSidebarTab } from 'nextcloud-vue'
import { mapState } from 'vuex'

export default {
	name: 'CardSidebar',
	components: {
		AppSidebar,
		AppSidebarTab
	},
	data() {
		return {
		}
	},
	computed: {
		...mapState({
			currentCard: state => state.currentCard
		}),
		subtitle() {
			let lastModified = this.currentCard.lastModified
			let createdAt = this.currentCard.createdAt

			return t('deck', 'Modified') + ': ' + lastModified + ' ' + t('deck', 'Created') + ': ' + createdAt
		}
	},
	methods: {
		closeSidebar() {
			this.$router.push({ name: 'board' })
		}
	}
}
</script>

<style lang="scss" scoped>

</style>
