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

			<div class="section-wrapper">
				<div v-tooltip="t('deck', 'Tags')" class="section-label icon-tag"><span class="hidden-visually">{{ t('deck', 'Tags') }}</span></div>
				<div class="section-details">
					<multiselect v-model="allLabels" :multiple="true" :options="currentBoard.labels"
						:taggable="true" label="title"
						track-by="id" @select="addLabelToCard" @remove="removeLabelFromCard">
						<template #option="scope">
							<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">{{ scope.option.title }}</div>
						</template>
						<template #tag="scope">
							<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">{{ scope.option.title }}</div>
						</template>
					</multiselect>
				</div>
			</div>

			<div class="section-wrapper">
				<div v-tooltip="t('deck', 'Assign to users')" class="section-label icon-group"><span class="hidden-visually">{{ t('deck', 'Assign to users') }}</span></div>
				<div class="section-details">
					<multiselect v-model="assignedUsers" :multiple="true" :options="assignableUsers"
						label="displayname"
						track-by="primaryKey"
						@select="assignUserToCard" @remove="removeUserFromCard">
						<template #option="scope">
							<avatar :user="scope.option.primaryKey" />
							<span class="avatarLabel">{{ scope.option.displayname }} </span>
						</template>
					</multiselect>
				</div>
			</div>

			<div class="section-wrapper">
				<div v-tooltip="t('deck', 'Due date')" class="section-label icon-calendar-dark"><span class="hidden-visually">{{ t('deck', 'Due date') }}</span></div>
				<div class="section-details">
					<DatetimePicker v-model="copiedCard.duedate" type="datetime" lang="en"
						format="YYYY-MM-DD HH:mm" confirm @change="setDue()" />
					<Actions>
						<ActionButton v-if="copiedCard.duedate" icon="icon-delete" @click="removeDue()">{{ t('deck', 'Remove due date') }}</ActionButton>
					</Actions>
				</div>
			</div>

			<h5>Description</h5>
			<VueEasymde ref="markdownEditor" v-model="copiedCard.description" :configs="mdeConfig" />
		</AppSidebarTab>
		<AppSidebarTab :order="1" name="Attachments" icon="icon-files-dark">
			{{ currentCard.attachments }}
			<button class="icon-upload" @click="clickAddNewAttachmment()">
				{{ t('deck', 'Upload attachment') }}
			</button>
		</AppSidebarTab>

		<AppSidebarTab :order="2" name="Comments" icon="icon-comment">
			<CommentsTabSidebar :card="currentCard" />
		</AppSidebarTab>

		<AppSidebarTab :order="3" name="Timeline" icon="icon-activity">
			<div v-if="isLoading" class="icon icon-loading" />
			<ActivityEntry v-for="entry in cardActivity" v-else :key="entry.activity_id"
				:activity="entry" />
			<button v-if="activityLoadMore" @click="loadMore">Load More</button>
		</AppSidebarTab>
	</app-sidebar>
</template>

<script>
import { AppSidebar, AppSidebarTab, Multiselect, DatetimePicker, Avatar } from 'nextcloud-vue'
import { mapState } from 'vuex'
import VueEasymde from 'vue-easymde'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
import ActivityEntry from '../ActivityEntry'
import Color from '../../mixins/color'
import CommentsTabSidebar from './CommentsTabSidebar'

export default {
	name: 'CardSidebar',
	components: {
		ActivityEntry,
		AppSidebar,
		AppSidebarTab,
		Multiselect,
		DatetimePicker,
		VueEasymde,
		Actions,
		ActionButton,
		Avatar,
		CommentsTabSidebar
	},
	mixins: [
		Color
	],
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
				autosave: { enabled: true, uniqueId: 'unique' },
				toolbar: false
			},
			lastModifiedRelative: null,
			lastCreatedRemative: null,
			params: {
				type: 'filter',
				since: 0,
				object_type: 'deck_card',
				object_id: this.id
			}
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			assignableUsers: state => state.assignableUsers,
			cardActivity: 'activity',
			activityLoadMore: 'activityLoadMore'
		}),
		currentCard() {
			return this.$store.getters.cardById(this.id)
		},
		subtitle() {
			return t('deck', 'Modified') + ': ' + this.lastModifiedRelative + ' ' + t('deck', 'Created') + ': ' + this.lastCreatedRemative
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

				if (this.currentCard.assignedUsers.length > 0) {
					this.assignedUsers = this.currentCard.assignedUsers.map((item) => item.participant)
				}

				this.desc = this.currentCard.description
				this.updateRelativeTimestamps()

				this.params.object_id = this.id
				this.loadCardActivity()
			}
		},

		'copiedCard.description': function() {
			this.saveDesc()
		}
	},
	created() {
		setInterval(this.updateRelativeTimestamps, 10000)
		this.loadCardActivity()
	},
	destroyed() {
		clearInterval(this.updateRelativeTimestamps)
	},
	methods: {
		updateRelativeTimestamps() {
			this.lastModifiedRelative = OC.Util.relativeModifiedDate(this.currentCard.lastModified * 1000)
			this.lastCreatedRemative = OC.Util.relativeModifiedDate(this.currentCard.createdAt * 1000)
		},
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
		loadCardActivity() {
			this.isLoading = true
			this.$store.dispatch('loadActivity', this.params).then(response => {
				this.isLoading = false
			})
		},
		loadMore() {
			let array = Object.values(this.cardActivity)
			let aId = (array[array.length - 1].activity_id)

			this.params.since = aId
			this.loadCardActivity()
		},
		clickAddNewAttachmment() {

		}

	}
}
</script>

<style>
	@import "~easymde/dist/easymde.min.css";
	.vue-easymde, .CodeMirror {
		border: none;
	}
	.editor-preview,
	.editor-statusbar {
		display: none;
	}

	#app-sidebar .app-sidebar-header__desc h4 {
		font-size: 12px !important;
	}
</style>

<style lang="scss">

	h5 {
		border-bottom: 1px solid var(--color-border);
		margin-top: 20px;
		margin-bottom: 5px;
		color: var(--color-text-maxcontrast);
	}

	.section-wrapper {
		display: flex;
		max-width: 100%;
		margin-top: 10px;

		.section-label {
			background-position: 0px center;
			width:28px;
			flex-shrink: 0;
		}

		.section-details {
			flex-grow: 1;

			button.action-item--single {
				margin-top: -6px;
			}
		}
	}

	.tag {
		flex-grow: 0;
		flex-shrink: 1;
		overflow: hidden;
		padding: 1px 3px;
		border-radius: 3px;
		font-size: 85%;
		margin-right: 3px;
	}

	.avatarLabel {
		padding: 6px
	}

</style>
