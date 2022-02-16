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
	<div v-if="currentCard" class="container">
		<div class="top">
			<h1 class="top-title">
				{{ currentCard.title }}
			</h1>
			<p class="top-modified">
				{{ t('deck', 'Modified') }}: {{ currentCard.lastModified | fromNow }}. {{ t('deck', 'Created') }} {{ currentCard.createdAt | fromNow }}
			</p>
		</div>
		<div class="tabs">
			<div class="tab members" :class="{active: activeTabs.includes('members')}" @click="changeActiveTab('members')">
				<i class="icon-user icon" />
				{{ t('deck', 'Members') }}
			</div>
			<div class="tab tags" :class="{active: activeTabs.includes('tags')}" @click="changeActiveTab('tags')">
				<i class="icon icon-tag" />
				{{ t('deck', 'Tags') }}
			</div>
			<div class="tab due-date" :class="{active: activeTabs.includes('duedate')}" @click="changeActiveTab('duedate')">
				<i class="icon icon-calendar-dark" />
				{{ t('deck', 'Due date') }}
			</div>
			<div class="tab project" :class="{active: activeTabs.includes('project')}" @click="changeActiveTab('project')">
				<i class="icon icon-deck" />
				{{ t('deck', 'Project') }}
			</div>
			<div class="tab attachments" :class="{active: activeTabs.includes('attachment')}" @click="changeActiveTab('attachment')">
				<i class="icon-attach icon icon-attach-dark" />
				{{ t('deck', 'Attachments') }}
			</div>
		</div>
		<div class="content">
			<div class="content-tabs">
				<MembersTab
					:card="currentCard"
					:active-tabs="activeTabs"
					:current-tab="currentTab"
					@click="activeTabs.push('members')"
					@active-tab="changeActiveTab"
					@remove-active-tab="removeActiveTab" />
				<TagsTab
					:active-tabs="activeTabs"
					:card="currentCard"
					:current-tab="currentTab"
					@click="activeTabs.push('tags')"
					@active-tab="changeActiveTab"
					@remove-active-tab="removeActiveTab" />
				<DueDateTab
					:active-tabs="activeTabs"
					:card="currentCard"
					:current-tab="currentTab"
					@click="activeTabs.push('duedate')"
					@active-tab="changeActiveTab"
					@remove-active-tab="removeActiveTab" />
				<ProjectTab
					:active-tabs="activeTabs"
					:card="currentCard"
					:current-tab="currentTab"
					@click="activeTabs.push('project')"
					@active-tab="changeActiveTab" />
				<AttachmentsTab
					:active-tabs="activeTabs"
					:card="currentCard"
					:current-tab="currentTab"
					@click="activeTabs.push('attachment')"
					@active-tab="changeActiveTab" />
			</div>
			<Description :key="currentCard.id" :card="currentCard" @change="descriptionChanged" />
		</div>
		<div class="activities">
			<h2 class="activities-title">
				<div class="icon-activity" /> {{ t('deck', 'Activity') }}
			</h2>
			<CardSidebarTabComments :card="currentCard" :tab-query="tabQuery" />
			<ActivityList v-if="hasActivity"
				filter="deck"
				:object-id="currentBoard.id"
				object-type="deck"
				type="deck" />
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { mapState, mapGetters } from 'vuex'
import relativeDate from '../../mixins/relativeDate'
import { showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import MembersTab from './MembersTab.vue'
import TagsTab from './TagsTab.vue'
import DueDateTab from './DueDateTab.vue'
import Description from './Description.vue'
import ProjectTab from './ProjectTab.vue'
import AttachmentsTab from './AttachmentsTab.vue'
import CardSidebarTabComments from './CardSidebarTabComments'
import moment from '@nextcloud/moment'
import ActivityList from '../ActivityList'

const capabilities = window.OC.getCapabilities()

export default {
	name: 'CardModal',
	components: {
		MembersTab,
		Description,
		TagsTab,
		DueDateTab,
		ProjectTab,
		AttachmentsTab,
		CardSidebarTabComments,
		ActivityList,
	},
	filters: {
		fromNow(value) {
			return moment.unix(value).fromNow()
		},
	},
	mixins: [relativeDate],
	props: {
		id: {
			type: Number,
			required: true,
		},
		tabId: {
			type: String,
			required: false,
			default: null,
		},
		tabQuery: {
			type: String,
			required: false,
			default: null,
		},
	},
	data() {
		return {
			newComment: '',
			titleEditable: false,
			titleEditing: '',
			hasActivity: capabilities && capabilities.activity,
			currentUser: getCurrentUser(),
			comment: '',
			currentTab: null,
			activeTabs: [],
		}
	},
	computed: {
		...mapGetters([
			'getCommentsForCard',
			'hasMoreComments',
		]),
		...mapState({
			currentBoard: state => state.currentBoard,
			replyTo: state => state.comment.replyTo,
		}),
		...mapGetters(['canEdit', 'assignables', 'cardActions', 'stackById']),
		title() {
			return this.titleEditable ? this.titleEditing : this.currentCard.title
		},
		currentCard() {
			return this.$store.getters.cardById(this.id)
		},
		subtitle() {
			return t('deck', 'Modified') + ': ' + this.relativeDate(this.currentCard.lastModified * 1000) + ' ' + t('deck', 'Created') + ': ' + this.relativeDate(this.currentCard.createdAt * 1000)
		},
		cardRichObject() {
			return {
				id: '' + this.currentCard.id,
				name: this.currentCard.title,
				boardname: this.currentBoard.title,
				stackname: this.stackById(this.currentCard.stackId)?.title,
				link: window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck/') + `#/board/${this.currentBoard.id}/card/${this.currentCard.id}`,
			}
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
	mounted() {
		this.loadComments()
	},
	methods: {
		cancelReply() {
			this.$store.dispatch('setReplyTo', null)
		},
		async createComment(content) {
			const commentObj = {
				cardId: this.currentCard.id,
				comment: content,
			}
			await this.$store.dispatch('createComment', commentObj)
			this.$store.dispatch('setReplyTo', null)
			this.newComment = ''
			await this.loadComments()
		},
		async loadComments() {
			this.$store.dispatch('setReplyTo', null)
			this.error = null
			this.isLoading = true
			try {
				await this.$store.dispatch('fetchComments', { cardId: this.currentCard.id })
				this.isLoading = false
				if (this.currentCard.commentsUnread > 0) {
					await this.$store.dispatch('markCommentsAsRead', this.currentCard.id)
				}
			} catch (e) {
				this.isLoading = false
				console.error('Failed to fetch more comments during infinite loading', e)
				this.error = t('deck', 'Failed to load comments')
			}
		},
		descriptionChanged(newDesc) {
			this.copiedCard.description = newDesc
		},
		handleUpdateTitleEditable(value) {
			this.titleEditable = value
			if (value) {
				this.titleEditing = this.currentCard.title
			}
		},
		handleUpdateTitle(value) {
			this.titleEditing = value
		},
		handleSubmitTitle(value) {
			if (value.trim === '') {
				showError(t('deck', 'The title cannot be empty.'))
				return
			}
			this.titleEditable = false
			this.$store.dispatch('updateCardTitle', { ...this.currentCard, title: this.titleEditing })
		},

		closeSidebar() {
			this.$router.push({ name: 'board' })
		},

		showModal() {
			this.$store.dispatch('setConfig', { cardDetailsInModal: true })
		},

		closeModal() {
			this.$store.dispatch('setConfig', { cardDetailsInModal: false })
		},

		changeActiveTab(tab) {
			this.currentTab = tab
			this.activeTabs = this.activeTabs.filter((item) => !['project', 'attachment'].includes(item))

			if (!this.activeTabs.includes(tab)) {
				this.activeTabs.push(tab)
			}

		},

		removeActiveTab(tab) {
			const index = this.activeTabs.indexOf(tab)
			if (index > -1) {
				this.activeTabs = this.activeTabs.splice(index, 1)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.content-tabs {
	display: grid;
	grid-template-columns: 1fr 2fr 1fr;
	align-items: flex-start;
}

.icon-activity {
	background-image: url(../../../img/flash-black.svg);
	width: 15px;
	height: 15px;
	margin-right: 5px;
}

.icon {
	margin-right: 5px;
}

.icon-plus {
	background-image: url(../../../img/plus.svg);
	width: 15px;
	height: 15px;
	margin-right: 5px;
}

.log-item {
	display: flex;
	justify-content: flex-start;
	line-height: 45px;
	align-items: center;
}

.activities {
	&-title {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		margin-bottom: 15px;
		font-weight: bold;
	}
	margin-top: 100px;
}

.comments {
	display: flex;
	justify-content: space-between;
	align-items: center;
	&-input {
		width: 100%;
		margin-left: 10px;
	}

	.comment-form {
		width: 95%;
	}
}

.container {
	padding: 20px;
}

.top {
	&-title {
		color: black;
		font-size: 20px;
		font-weight: bold;
	}
	&-modified {
		color: #767676;
		line-height: 40px;
	}
}

.tabs {
	margin-top: 20px;
	margin-bottom: 20px;
	display: grid;
	grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
	grid-gap: 30px;
}

.tab {
	cursor: pointer;
	font-weight: bold;
	background-color: #ededed;
	color: rgb(0, 0, 0);
	flex-grow: 0;
	flex-shrink: 1;
	display: flex;
	flex-direction: row;
	overflow: hidden;
	padding: 10px 10px;
	border-radius: 10px;
	font-size: 85%;
	margin-bottom: 3px;
	margin-right: 5px;
	width: 100px;
}

.action-btn {
	list-style: none;
}

.edit-btns {
	display: flex;
	align-items: center;
}

.description {
	display: flex;
	justify-content: space-between;
	margin-top: 30px;
}

.active {
	color: #409eff;
	background-color: #ecf5ff;
}
</style>
