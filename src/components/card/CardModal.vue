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
	<div class="container">
		<div class="top">
			<h1 class="top-title">
				Example task 3
			</h1>
			<p class="top-modified">
				Modified: 2 days go. Created 3 days ago
			</p>
		</div>
		<div class="tabs">
			<div class="tab members" :class="{active: 'members'}">
				<i class="icon-user icon" />
				Members
			</div>
			<div class="tab tags">
				<i class="icon icon-tag" />
				Tags
			</div>
			<div class="tab due-date">
				<i class="icon icon-calendar-dark" />
				Due date
			</div>
			<div class="tab project">
				<i class="icon icon-deck" />
				Project
			</div>
			<div class="tab attachments">
				<i class="icon-attach icon icon-attach-dark" />
				Attachments
			</div>
		</div>
		<div class="content">
			<MembersTab :card="currentCard" />
			<Description :key="currentCard.id" :card="currentCard" @change="descriptionChanged" />
		</div>
		<div class="activities">
			<h2 class="activities-title">
				<div class="icon-flash-black" /> Activities
			</h2>
			<div class="comment">
				<Avatar :user="currentUser.uid" />
				<input v-model="comment"
					type="text"
					:placeholder="t('deck', 'Leave a comment')"
					class="comment-input">
			</div>
			<div class="activities-logs">
				<p class="log-item">
					<i class="icon-plus" /> You have created card Example Task 3 in list To do on board Personal
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import { Avatar } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { mapState, mapGetters } from 'vuex'
import relativeDate from '../../mixins/relativeDate'
import { showError } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import MembersTab from './MembersTab.vue'
import Description from './Description.vue'

const capabilities = window.OC.getCapabilities()

export default {
	name: 'CardModal',
	components: { Avatar, MembersTab, Description },
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
			titleEditable: false,
			titleEditing: '',
			hasActivity: capabilities && capabilities.activity,
			currentUser: getCurrentUser(),
			comment: '',
			activeTab: 'members',
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
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
	methods: {
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
	},
}
</script>

<style lang="scss" scoped>
.icon-flash-black {
	background-image: url(../../../img/flash-black.svg);
	width: 15px;
	height: 15px;
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

.comment {
	display: flex;
	justify-content: space-between;
	align-items: center;
	&-input {
		width: 100%;
		margin-left: 10px;
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
	display: flex;
	justify-content: flex-start;
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
	border-radius: 5px;
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
