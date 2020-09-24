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
			<div class="section-wrapper">
				<div v-tooltip="t('deck', 'Tags')" class="section-label icon-tag">
					<span class="hidden-visually">{{ t('deck', 'Tags') }}</span>
				</div>
				<div class="section-details">
					<Multiselect v-model="assignedLabels"
						:multiple="true"
						:disabled="!canEdit"
						:options="labelsSorted"
						:placeholder="t('deck', 'Assign a tag to this card…')"
						:taggable="true"
						label="title"
						track-by="id"
						@select="addLabelToCard"
						@remove="removeLabelFromCard">
						<template #option="scope">
							<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">
								{{ scope.option.title }}
							</div>
						</template>
						<template #tag="scope">
							<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">
								{{ scope.option.title }}
							</div>
						</template>
					</Multiselect>
				</div>
			</div>

			<div class="section-wrapper">
				<div v-tooltip="t('deck', 'Assign to users')" class="section-label icon-group">
					<span class="hidden-visually">{{ t('deck', 'Assign to users/groups/circles') }}</span>
				</div>
				<div class="section-details">
					<Multiselect v-if="canEdit"
						v-model="assignedUsers"
						:multiple="true"
						:options="formatedAssignables"
						:user-select="true"
						:auto-limit="false"
						:placeholder="t('deck', 'Assign a user to this card…')"
						label="displayname"
						track-by="multiselectKey"
						@select="assignUserToCard"
						@remove="removeUserFromCard">
						<template #tag="scope">
							<div class="avatarlist--inline">
								<Avatar :user="scope.option.uid"
									:display-name="scope.option.displayname"
									:size="24"
									:is-no-user="scope.option.isNoUser"
									:disable-menu="true" />
							</div>
						</template>
					</Multiselect>
					<div v-else class="avatar-list--readonly">
						<Avatar v-for="option in assignedUsers"
							:key="option.primaryKey"
							:user="option.uid"
							:display-name="option.displayname"
							:is-no-user="option.isNoUser"
							:size="32" />
					</div>
				</div>
			</div>

			<div class="section-wrapper">
				<div v-tooltip="t('deck', 'Due date')" class="section-label icon-calendar-dark">
					<span class="hidden-visually">{{ t('deck', 'Due date') }}</span>
				</div>
				<div class="section-details">
					<DatetimePicker v-model="duedate"
						:placeholder="t('deck', 'Set a due date')"
						type="datetime"
						:minute-step="5"
						:show-second="false"
						:lang="lang"
						:format="format"
						:disabled="saving || !canEdit"
						:shortcuts="shortcuts"
						confirm />
					<Actions v-if="canEdit">
						<ActionButton v-if="copiedCard.duedate" icon="icon-delete" @click="removeDue()">
							{{ t('deck', 'Remove due date') }}
						</ActionButton>
					</Actions>
				</div>
			</div>

			<div class="section-wrapper">
				<CollectionList v-if="currentCard.id"
					:id="`${currentCard.id}`"
					:name="currentCard.title"
					type="deck-card" />
			</div>

			<h5>
				{{ t('deck', 'Description') }}
				<span v-if="copiedCard.descriptionLastEdit && !descriptionSaving">{{ t('deck', '(Unsaved)') }}</span>
				<span v-if="descriptionSaving">{{ t('deck', '(Saving…)') }}</span>
				<a v-tooltip="t('deck', 'Formatting help')"
					href="https://deck.readthedocs.io/en/latest/Markdown/"
					target="_blank"
					class="icon icon-info" />
				<Actions v-if="canEdit">
					<ActionButton v-if="!descriptionEditing" icon="icon-rename" @click="showEditor()">
						{{ t('deck', 'Edit description') }}
					</ActionButton>
					<ActionButton v-else icon="icon-toggle" @click="hideEditor()">
						{{ t('deck', 'View description') }}
					</ActionButton>
				</Actions>
				<Actions v-if="canEdit">
					<ActionButton v-if="descriptionEditing" icon="icon-attach" @click="showAttachmentModal()">
						{{ t('deck', 'Add Attachment') }}
					</ActionButton>
				</Actions>
			</h5>

			<div v-if="!descriptionEditing"
				id="description-preview"
				@click="clickedPreview"
				v-html="renderedDescription" />
			<VueEasymde v-else
				:key="copiedCard.id"
				ref="markdownEditor"
				v-model="copiedCard.description"
				:configs="mdeConfig"
				@input="updateDescription"
				@blur="saveDescription" />
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
		<Modal v-if="modalShow" :title="t('deck', 'Choose attachment')" @close="modalShow=false">
			<div class="modal__content">
				<h3>{{ t('deck', 'Choose attachment') }}</h3>
				<AttachmentList
					:card-id="currentCard.id"
					:selectable="true"
					@selectAttachment="addAttachment" />
			</div>
		</Modal>
	</AppSidebar>
</template>

<script>
import { Avatar, Actions, ActionButton, Multiselect, AppSidebar, AppSidebarTab, DatetimePicker, Modal } from '@nextcloud/vue'
import { mapState, mapGetters } from 'vuex'
import Color from '../../mixins/color'
import { CollectionList } from 'nextcloud-vue-collections'
import CardSidebarTabAttachments from './CardSidebarTabAttachments'
import CardSidebarTabComments from './CardSidebarTabComments'
import CardSidebarTabActivity from './CardSidebarTabActivity'
import MarkdownIt from 'markdown-it'
import MarkdownItTaskLists from 'markdown-it-task-lists'
import { formatFileSize } from '@nextcloud/files'
import relativeDate from '../../mixins/relativeDate'
import AttachmentList from './AttachmentList'
import { generateUrl } from '@nextcloud/router'
import {
	getLocale,
	getDayNamesMin,
	getFirstDay,
	getMonthNamesShort,
} from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { showError } from '@nextcloud/dialogs'

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
		Multiselect,
		DatetimePicker,
		VueEasymde: () => import('vue-easymde/dist/VueEasyMDE.common'),
		Actions,
		ActionButton,
		Avatar,
		CollectionList,
		CardSidebarTabAttachments,
		CardSidebarTabComments,
		CardSidebarTabActivity,
		Modal,
		AttachmentList,
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
			assignedLabels: null,
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
			modalShow: false,
			lang: {
				days: getDayNamesMin(),
				months: getMonthNamesShort(),
				formatLocale: {
					firstDayOfWeek: getFirstDay() === 0 ? 7 : getFirstDay(),
				},
				placeholder: {
					date: t('deck', 'Select Date'),
				},
			},
			format: {
				stringify: this.stringify,
				parse: this.parse,
			},
			shortcuts: [
				{
					text: 'Today',
					onClick() {
						const date = new Date()
						return date
					},
				},
				{
					text: 'Tomorrow',
					onClick() {
						const date = new Date()
						date.setTime(date.getTime() + 3600 * 1000 * 24)
						return date
					},
				},
			],
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
		labelsSorted() {
			return [...this.currentBoard.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
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
			this.assignedLabels = [...this.currentCard.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)

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
		showAttachmentModal() {
			this.modalShow = true
		},
		addAttachment(attachment) {
			const descString = this.$refs.markdownEditor.easymde.value()
			let embed = ''
			if (attachment.extendedData.mimetype.includes('image')) {
				embed = '!'
			}
			const attachmentString = embed + '[📎 ' + attachment.data + '](' + this.attachmentUrl(attachment) + ')'
			this.$refs.markdownEditor.easymde.value(descString + '\n' + attachmentString)
			this.modalShow = false
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
			if (newTitle.trim === '') {
				showError(t('deck', 'The title cannot be empty.'))
				return
			}
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
			return moment(value).toDate()
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

	// FIXME: Obivously we should at some point not randomly reuse the sidebar component
	// since this is not oficially supported
	.modal__card .app-sidebar {
		border: 0;
		min-width: 100%;
		position: relative;
		top: 0;
		left: 0;
		right: 0;
		max-width: 100%;
		max-height: 100%;
		&::v-deep {
			.app-sidebar-header {
				position: sticky;
				top: 0;
				z-index: 100;
				background-color: var(--color-main-background);
			}
			.app-sidebar-tabs__nav {
				position: sticky;
				top: 87px;
				margin: 0;
				z-index: 100;
				background-color: var(--color-main-background);
			}

			section {
				min-height: auto;
			}

			#emptycontent, .emptycontent {
				margin-top: 88px;
			}
		}
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
