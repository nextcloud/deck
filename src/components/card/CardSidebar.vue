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
	<app-sidebar v-if="currentCard !== null && copiedCard !== null"
		:actions="toolbarActions"
		:title="currentCard.title"
		:subtitle="subtitle"
		@close="closeSidebar">
		<template #action />
		<AppSidebarTab :order="0" name="Details" icon="icon-home">

			<p>Tags</p>
			<multiselect v-model="allLabels" :multiple="true" :options="currentBoard.labels"
				:taggable="true" label="title"
				track-by="id" @select="addLabelToCard" @remove="removeLabelFromCard">
				<template #option="scope">
					<span>{{ scope.option.title }}</span>
				</template>
			</multiselect>

			<p>Assign to user</p>
			<multiselect v-model="assignedUsers" :multiple="true" :options="assignableUsers"
				label="displayname"
				track-by="primaryKey"
				@select="assignUserToCard" @remove="removeUserFromCard">
				<template #option="scope">
					{{ scope.option.displayname }}
				</template>
			</multiselect>

			<p>Due to</p>

			<DatetimePicker v-model="copiedCard.duedate" type="datetime" lang="en"
				format="YYYY-MM-DD HH:mm" confirm @change="setDue()" />
			<button v-tooltip="t('deck', 'Delete')" v-if="copiedCard.duedate" class="icon-delete"
				@click="removeDue()" />

			<VueEasymde ref="markdownEditor" v-model="desc" :configs="mdeConfig" />
		</AppSidebarTab>
		<AppSidebarTab :order="1" name="Attachments" icon="icon-files-dark">
			{{ currentCard.attachments }}
			<button class="icon-upload" @click="clickAddNewAttachmment()">
				{{ t('deck', 'Upload attachment') }}
			</button>
		</AppSidebarTab>
		<AppSidebarTab :order="2" name="Timeline" icon="icon-activity">
			this is the activity tab
		</AppSidebarTab>
	</app-sidebar>
</template>

<script>
import { AppSidebar, AppSidebarTab, Multiselect, DatetimePicker } from 'nextcloud-vue'
import { mapState } from 'vuex'
import VueEasymde from "vue-easymde"

export default {
	name: 'CardSidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		Multiselect,
		DatetimePicker,
		VueEasymde
	},
	props: {
		id: {
			type: Number,
			required: true
		}
	},
	data() {
		return {
			assignedUsers: null,
			addedLabelToCard: null,
			isLoading: false,
			copiedCard: null,
			allLabels: null,
			desc: null,
			mdeConfig: {
				autoDownloadFontAwesome: false, 
				spellChecker: false, 
				autofocus: true, 
				autosave: {enabled: true, uniqueId: 'unique'}, 
				toolbar: false
			}
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			assignableUsers: state => state.assignableUsers
		}),
		currentCard() {
			return this.$store.getters.cardById(this.id)
		},
		subtitle() {
			let lastModified = this.currentCard.lastModified
			let createdAt = this.currentCard.createdAt

			return t('deck', 'Modified') + ': ' + lastModified + ' ' + t('deck', 'Created') + ': ' + createdAt
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
		'currentCard': {
			immediate: true,
			handler() {
				this.copiedCard = JSON.parse(JSON.stringify(this.currentCard))
				this.allLabels = this.currentCard.labels
				this.assignedUsers = this.currentCard.assignedUsers.map((item) => item.participant)
				this.desc = this.currentCard.description
			}
		},
		desc() {
			this.copiedCard.description = this.desc
			this.saveDesc()
		}
	},
	methods: {
		setDue() {
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},
		removeDue() {
			this.copiedCard.duedate = null
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},
		saveDesc() {
			this.$store.dispatch('updateCardDesc', this.copiedCard)
		},

		closeSidebar() {
			this.$router.push({ name: 'board' })
		},

		assignUserToCard(user) {
			this.copiedCard.newUserUid = user.uid
			this.$store.dispatch('assignCardToUser', this.copiedCard)
		},

		removeUserFromCard(user) {
			this.copiedCard.removeUserUid = user.uid
			this.$store.dispatch('removeUserFromCard', this.copiedCard)
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
		},
		clickAddNewAttachmment() {

		}

	}
}
</script>

<style>
	@import "~easymde/dist/easymde.min.css";
	.editor-preview,
	.editor-statusbar {
		display: none;
	}
</style>
