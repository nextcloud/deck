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
		:actions="toolbarActions"
		:title="currentCard.title"
		@close="closeSidebar">
		<template #action />
		<AppSidebarTab name="Details" icon="icon-home">

			{{ currentCard }}
			<p>Tags</p>
			<multiselect v-model="allLabels" :multiple="true" :options="currentBoard.labels"
				:taggable="true" label="title"
				track-by="id" @select="addLabelToCard" @remove="removeLabelFromCard">
				<template #option="scope">
					<span>{{ scope.option.title }}</span>
				</template>
			</multiselect>

			<p>Assign to user</p>
			<multiselect v-model="addAclToCard" :options="unallocatedSharees" label="label"
				track-by="value.shareWith"
				@select="assignUserToCard" @remove="removeUserFromCard" @search-change="asyncFind">
				<template #option="scope">
					{{ scope.option.label }}
				</template>
			</multiselect>

			<p>Due to</p>
			<DatetimePicker v-model="copiedCard.duedate" type="datetime" lang="en"
				format="YYYY-MM-DD HH:mm" confirm @change="setDue()" />

			<hr>
			<p>{{ subtitle }}</p>

		</AppSidebarTab>
		<AppSidebarTab name="Description" icon="icon-description">
			<textarea v-model="copiedCard.description" type="text" autofocus />
			<input type="button" class="icon-confirm" @click="saveDesc()">
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
import { AppSidebar, AppSidebarTab, Multiselect, DatetimePicker } from 'nextcloud-vue'
import { mapState } from 'vuex'

export default {
	name: 'CardSidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		Multiselect,
		DatetimePicker
	},
	data() {
		return {
			addAclToCard: null,
			addedLabelToCard: null,
			isLoading: false,
			copiedCard: null,
			allLabels: null
		}
	},
	computed: {
		...mapState({
			currentCard: state => state.currentCard,
			currentBoard: state => state.currentBoard,
			sharees: 'sharees'
		}),
		subtitle() {
			let lastModified = this.currentCard.lastModified
			let createdAt = this.currentCard.createdAt

			return t('deck', 'Modified') + ': ' + lastModified + ' ' + t('deck', 'Created') + ': ' + createdAt
		},
		unallocatedSharees() {

			return this.sharees

			/* return this.sharees.filter((sharee) => {
				return Object.values(this.board.acl).findIndex((acl) => {
					return acl.participant.uid === sharee.value.shareWith
				})
			}) */
		},
		toolbarActions() {
			return [
				{
					action: () => {

					},
					icon: 'icon-archive-dark',
					text: t('deck', 'Assign to me')
				},
				{
					action: () => {

					},
					icon: 'icon-archive',
					text: t('deck', (this.showArchived ? 'Unarchive card' : 'Archive card'))
				}
			]
		}
	},
	watch: {
		currentCard() {
			this.copiedCard = JSON.parse(JSON.stringify(this.currentCard))
			this.allLabels = this.currentCard.labels
			this.addAclToCard = this.currentCard.assignedUsers
		}
	},
	created() {

	},
	methods: {
		setDue() {
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},

		saveDesc() {
			this.$store.dispatch('updateCardDesc', this.copiedCard)
		},

		asyncFind(query) {
			this.isLoading = true
			this.$store.dispatch('loadSharees').then(response => {
				this.isLoading = false
			})
		},

		closeSidebar() {
			this.$router.push({ name: 'board' })
		},

		assignUserToCard(user) {
			this.copiedCard.assignedUsers.push(user)
			this.copiedCard.newUserUid = user.value.shareWith
			this.$store.dispatch('assignCardToUser', this.copiedCard)
			/* this.addAclForAPI = {
				type: 0,
				participant: this.addAcl.value.shareWith,
				permissionEdit: false,
				permissionShare: false,
				permissionManage: false
			}
			this.$store.dispatch('addAclToCurrentBoard', this.addAclForAPI) */
		},

		removeUserFromCard(user) {

		},

		addLabelToCard(newLabel) {
			this.copiedCard.labels.push(newLabel)
			let data = {
				card: this.copiedCard,
				labelId: newLabel.id
			}
			this.$store.dispatch('addLabel', data)
		},

		removeLabelFromCard(removedLabel) {

			let removeIndex = this.copiedCard.labels.findIndex((label) => {
				return label.id === removedLabel.id
			})
			if (removeIndex !== -1) {
				this.copiedCard.labels.splice(removeIndex, 1)
			}

			let data = {
				card: this.copiedCard,
				labelId: removedLabel.id
			}
			this.$store.dispatch('removeLabel', data)
		}
	}
}
</script>
