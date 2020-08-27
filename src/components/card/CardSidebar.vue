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
	<AppSidebar v-if="currentBoard && currentCard && copiedCard"
		:title="currentCard.title"
		:subtitle="subtitle"
		:title-editable.sync="titleEditable"
		@update:title="updateTitle"
		:class="{ 'app-sidebar-modal': cardDetailsInModal}"
		@close="closeSidebar">
		<template #secondary-actions>
			<ActionButton v-if="cardDetailsInModal" icon="icon-menu-sidebar" @click.stop="showModal()">
				{{ t('deck', 'Open in sidebar view') }}
			</ActionButton>

			<ActionButton v-else icon="icon-external" @click.stop="showModal()">
				{{ t('deck', 'Open in bigger view') }}
			</ActionButton>
		</template>

		<AppSidebarTab id="details"
			:order="0"
			:name="t('deck', 'Details')"
			icon="icon-home">
			<CardSidebarTabDetails :id="id" />
		</AppSidebarTab>

		<AppSidebarTab id="attachments"
			:order="1"
			:name="t('deck', 'Attachments')"
			icon="icon-attach">
			<CardSidebarTabAttachments :card="currentCard" />
		</AppSidebarTab>

		<AppSidebarTab v-if="hasComments"
			id="comments"
			:order="2"
			:name="t('deck', 'Comments')"
			icon="icon-comment">
			<CardSidebarTabComments :card="currentCard" />
		</AppSidebarTab>

		<AppSidebarTab v-if="hasActivity"
			id="timeline"
			:order="3"
			:name="t('deck', 'Timeline')"
			icon="icon-activity">
			<CardSidebarTabActivity :card="currentCard" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import { ActionButton, AppSidebar, AppSidebarTab } from '@nextcloud/vue'
import { mapState, mapGetters } from 'vuex'
import Color from '../../mixins/color'

import CardSidebarTabDetails from './CardSidebarTabDetails'
import CardSidebarTabAttachments from './CardSidebarTabAttachments'
import CardSidebarTabComments from './CardSidebarTabComments'
import CardSidebarTabActivity from './CardSidebarTabActivity'
import MarkdownIt from 'markdown-it'
import MarkdownItTaskLists from 'markdown-it-task-lists'
import { formatFileSize } from '@nextcloud/files'
import relativeDate from '../../mixins/relativeDate'

import { generateUrl } from '@nextcloud/router'
import { getLocale } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

const markdownIt = new MarkdownIt({
	linkify: true,
})
markdownIt.use(MarkdownItTaskLists, { enabled: true, label: true, labelAfter: true })

const capabilities = window.OC.getCapabilities()

export default {
	name: 'CardSidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		ActionButton,
		CardSidebarTabDetails,
		CardSidebarTabAttachments,
		CardSidebarTabComments,
		CardSidebarTabActivity,
	},
	mixins: [Color, relativeDate],
	props: {
		id: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			assignedUsers: null,
			addedLabelToCard: null,
			copiedCard: null,
			allLabels: null,
			locale: getLocale(),

			saving: false,
			markdownIt: null,
			titleEditable: false,
			descriptionEditing: false,
			mdeConfig: {
				autoDownloadFontAwesome: false,
				spellChecker: false,
				autofocus: true,
				autosave: { enabled: false, uniqueId: 'unique' },
				toolbar: false,
			},
			descriptionSaveTimeout: null,
			descriptionSaving: false,
			hasActivity: capabilities && capabilities.activity,
			hasComments: !!OC.appswebroots['comments'],
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
			cardDetailsInModal: state => state.cardDetailsInModal,
		}),
		...mapGetters(['canEdit', 'assignables']),
		attachments() {
			return [...this.$store.getters.attachmentsByCard(this.id)].sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (mimetype) => {
				const url = OC.MimeType.getIconUrl(mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentUrl() {
			return (attachment) => generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		currentCard() {
			return this.$store.getters.cardById(this.id)
		},
		subtitle() {
			return t('deck', 'Modified') + ': ' + this.relativeDate(this.currentCard.lastModified * 1000) + ' ' + t('deck', 'Created') + ': ' + this.relativeDate(this.currentCard.createdAt * 1000)
		},
		formatedAssignables() {
			return this.assignables.map(item => {
				const assignable = {
					...item,
					user: item.primaryKey,
					displayName: item.displayname,
					icon: 'icon-user',
					isNoUser: false,
					multiselectKey: item.type + ':' + item.uid,
				}

				if (item.type === 1) {
					assignable.icon = 'icon-group'
					assignable.isNoUser = true
				}
				if (item.type === 7) {
					assignable.icon = 'icon-circles'
					assignable.isNoUser = true
				}

				return assignable
			})
		},
		duedate: {
			get() {
				return this.currentCard.duedate ? new Date(this.currentCard.duedate) : null
			},
			async set(val) {
				this.saving = true
				await this.$store.dispatch('updateCardDue', {
					...this.copiedCard,
					duedate: val ? (new Date(val)).toISOString() : null,
				})
				this.saving = false
			},
		},
		renderedDescription() {
			return markdownIt.render(this.copiedCard.description || '')
		},
	},
	watch: {
		currentCard() {
			this.initialize()
		},
	},
	mounted() {
		this.initialize()
	},
	methods: {
		async initialize() {
			if (!this.currentCard) {
				return
			}

			if (this.copiedCard) {
				await this.saveDescription()
			}

			this.copiedCard = JSON.parse(JSON.stringify(this.currentCard))
			this.allLabels = this.currentCard.labels

			if (this.currentCard.assignedUsers && this.currentCard.assignedUsers.length > 0) {
				this.assignedUsers = this.currentCard.assignedUsers.map((item) => ({
					...item.participant,
					isNoUser: item.participant.type !== 0,
					multiselectKey: item.participant.type + ':' + item.participant.primaryKey,
				}))
			} else {
				this.assignedUsers = []
			}

			this.desc = this.currentCard.description
		},
		showEditor() {
			if (!this.canEdit) {
				return
			}
			this.descriptionEditing = true
		},
		hideEditor() {
			this.descriptionEditing = false
		},

		clickedPreview(e) {
			if (e.target.getAttribute('type') === 'checkbox') {
				const clickedIndex = [...document.querySelector('#description-preview').querySelectorAll('input')].findIndex((li) => li.id === e.target.id)
				const reg = /\[(X|\s|_|-)\]/ig
				let nth = 0
				const updatedDescription = this.copiedCard.description.replace(reg, (match, i, original) => {
					let result = match
					if ('' + nth++ === '' + clickedIndex) {
						if (match.match(/^\[\s\]/i)) {
							result = match.replace(/\[\s\]/i, '[x]')
						}
						if (match.match(/^\[x\]/i)) {
							result = match.replace(/\[x\]/i, '[ ]')
						}
						return result
					}
					return match
				})
				this.$set(this.copiedCard, 'description', updatedDescription)
				this.$store.dispatch('updateCardDesc', this.copiedCard)
			}
		},
		setDue() {
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},
		removeDue() {
			this.copiedCard.duedate = null
			this.$store.dispatch('updateCardDue', this.copiedCard)
		},
		async saveDescription() {
			if (!Object.prototype.hasOwnProperty.call(this.copiedCard, 'descriptionLastEdit') || this.descriptionSaving) {
				return
			}
			this.descriptionSaving = true
			await this.$store.dispatch('updateCardDesc', this.copiedCard)
			delete this.copiedCard.descriptionLastEdit
			this.descriptionSaving = false
		},
		updateTitle(newTitle) {
			this.$set(this.copiedCard, 'title', newTitle)
			this.$store.dispatch('updateCardTitle', this.copiedCard).then(() => {
				this.titleEditable = false
			})
		},
		updateDescription() {
			this.copiedCard.descriptionLastEdit = Date.now()
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(async() => {
				 await this.saveDescription()
			}, 2500)
		},

		closeSidebar() {
			this.$router.push({ name: 'board' })
		},

		assignUserToCard(user) {
			this.$store.dispatch('assignCardToUser', {
				card: this.copiedCard,
				assignee: {
					userId: user.uid,
					type: user.type,
				},
			})
		},

		removeUserFromCard(user) {
			this.$store.dispatch('removeUserFromCard', {
				card: this.copiedCard,
				assignee: {
					userId: user.uid,
					type: user.type,
				},
			})
		},

		addLabelToCard(newLabel) {
			this.copiedCard.labels.push(newLabel)
			const data = {
				card: this.copiedCard,
				labelId: newLabel.id,
			}
			this.$store.dispatch('addLabel', data)
		},

		removeLabelFromCard(removedLabel) {

			const removeIndex = this.copiedCard.labels.findIndex((label) => {
				return label.id === removedLabel.id
			})
			if (removeIndex !== -1) {
				this.copiedCard.labels.splice(removeIndex, 1)
			}

			const data = {
				card: this.copiedCard,
				labelId: removedLabel.id,
			}
			this.$store.dispatch('removeLabel', data)
		},
		stringify(date) {
			return moment(date).locale(this.locale).format('LLL')
		},
		parse(value) {
			return moment(value, 'LLL', this.locale).toDate()
		},
		showModal() {
			this.$store.dispatch('setCardDetailsInModal', true)
		},
	},
}
</script>

<style>
	@import '~easymde/dist/easymde.min.css';

	.vue-easymde, .CodeMirror {
		border: none;
		margin: 0;
		padding: 0;
		background-color: var(--color-main-background);
		color: var(--color-main-text);
	}

	.editor-preview,
	.editor-statusbar {
		display: none;
	}

	#app-sidebar .app-sidebar-header__desc h4 {
		font-size: 12px !important;
	}
</style>

<style lang="scss" scoped>
	.app-sidebar-modal {
		border-left: 0;
		width: 800px;
		max-width: 780px;
		top: 0px;
	}

	h5 {
		border-bottom: 1px solid var(--color-border);
		margin-top: 20px;
		margin-bottom: 5px;
		color: var(--color-text-maxcontrast);

		.icon-info {
			display: inline-block;
			width: 32px;
			height: 16px;
			float: right;
			opacity: .7;
		}

		.icon-attach {
			background-size: 16px;
			float: right;
			margin-top: -14px;
			opacity: .7;
		}

		.icon-toggle, .icon-rename {
			float: right;
			margin-top: -14px;
		}
	}

	aside::v-deep section {
		display: flex;
		flex-direction: column;
	}

	.section-wrapper {
		display: flex;
		max-width: 100%;
		margin-top: 10px;

		.section-label {
			background-position: 0px center;
			width: 28px;
			margin-left: 9px;
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
		padding: 0px 5px;
		border-radius: 15px;
		font-size: 85%;
		margin-right: 3px;
	}

	.avatarLabel {
		padding: 6px
	}

	.section-details::v-deep .multiselect__tags-wrap {
		flex-wrap: wrap;
	}

	.avatar-list--readonly .avatardiv {
		margin-right: 3px;
	}

	.avatarlist--inline {
		display: flex;
		align-items: center;
		margin-right: 3px;
		.avatarLabel {
			padding: 0;
		}
	}

	.multiselect::v-deep .multiselect__tags-wrap {
		z-index: 2;
	}

	.multiselect.multiselect--active::v-deep .multiselect__tags-wrap {
		z-index: 0;
	}

	#description-preview {
		min-height: 100px;

		&::v-deep {
			@import './../../css/markdown';
		}

		&::v-deep input {
			min-height: auto;
		}

		&::v-deep a {
			text-decoration: underline;
		}
	}

	.modal__content {
		width: 25vw;
		min-width: 250px;
		min-height: 120px;
		margin: 20px;
		padding-bottom: 20px;
		display: flex;
		flex-direction: column;

		&::v-deep .attachment-list {
			flex-shrink: 1;
			overflow: scroll;
			max-height: 50vh;
		}
	}

</style>
