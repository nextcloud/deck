<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div data-test="description">
		<h5>
			{{ t('deck', 'Description') }}
			<span v-if="descriptionLastEdit && !descriptionSaving">{{ t('deck', '(Unsaved)') }}</span>
			<span v-if="descriptionSaving">{{ t('deck', '(Saving…)') }}</span>
			<a v-if="!textAppAvailable"
				:title="t('deck', 'Formatting help')"
				href="https://deck.readthedocs.io/en/latest/Markdown/"
				target="_blank"
				class="icon icon-info" />
			<NcActions v-if="!textAppAvailable && canEdit">
				<NcActionButton v-if="!descriptionEditing" icon="icon-rename" @click="showEditor()">
					{{ t('deck', 'Edit description') }}
				</NcActionButton>
				<NcActionButton v-else icon="icon-toggle" @click="hideEditor()">
					{{ t('deck', 'View description') }}
				</NcActionButton>
			</NcActions>
			<NcActions v-if="canEdit && !!card.id">
				<NcActionButton v-if="descriptionEditing" @click="showAttachmentModal()">
					<template #icon>
						<PaperclipIcon :size="24" decorative />
					</template>
					{{ t('deck', 'Add Attachment') }}
				</NcActionButton>
			</NcActions>
		</h5>

		<div v-if="textAppAvailable" class="description__text">
			<div ref="editor" />
		</div>
		<template v-else>
			<div v-if="!descriptionEditing && hasDescription"
				id="description-preview"
				dir="auto"
				@click="clickedPreview"
				v-html="renderedDescription" />
			<p v-else-if="!descriptionEditing" class="placeholder" @click="showEditor()">
				{{ t('deck', 'Write a description …') }}
			</p>
			<VueEasymde v-else
				:key="card.id"
				ref="markdownEditor"
				v-model="description"
				:configs="mdeConfig"
				@initialized="addKeyListeners"
				@update:modelValue="updateDescription"
				@blur="saveDescription" />
		</template>

		<NcModal v-if="modalShow && !!card.id" :title="t('deck', 'Choose attachment')" @close="modalShow=false">
			<div class="modal__content">
				<h3>{{ t('deck', 'Choose attachment') }}</h3>
				<AttachmentList :card-id="card.id"
					:selectable="true"
					@select-attachment="addAttachment" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import MarkdownIt from 'markdown-it'
import MarkdownItTaskCheckbox from 'markdown-it-task-checkbox'
import MarkdownItLinkAttributes from 'markdown-it-link-attributes'
import AttachmentList from './AttachmentList.vue'
import { NcActions, NcActionButton, NcModal } from '@nextcloud/vue'
import { formatFileSize } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { showWarning } from '@nextcloud/dialogs'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import { mapState } from 'vuex'

const markdownIt = new MarkdownIt({
	linkify: true,
})
markdownIt.use(MarkdownItTaskCheckbox, { disabled: false, idPrefix: 'task-item-', ulClass: 'contains-task-list' })

markdownIt.use(MarkdownItLinkAttributes, {
	attrs: {
		target: '_blank',
		rel: 'noreferrer noopener',
	},
})

export default {
	name: 'Description',
	components: {
		VueEasymde: () => import('vue-easymde/dist/VueEasyMDE.common.js'),
		NcActions,
		NcActionButton,
		NcModal,
		AttachmentList,
		PaperclipIcon,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
		canEdit: {
			type: Boolean,
			default: true,
		},
		showAttachments: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			textAppAvailable: !!window.OCA?.Text?.createEditor,
			editor: null,
			keyExitState: 0,
			description: '',
			markdownIt: null,
			descriptionEditing: false,
			mdeConfig: {
				autoDownloadFontAwesome: false,
				spellChecker: false,
				autofocus: true,
				autosave: { enabled: false, uniqueId: 'unique' },
				toolbar: false,
				placeholder: t('deck', 'Write a description …'),
				previewImagesInEditor: false,
			},
			descriptionSaveTimeout: null,
			descriptionSaving: false,
			descriptionLastEdit: 0,
			modalShow: false,
		}
	},
	computed: {
		...mapState({
			hasCardSaveError: (state) => state.hasCardSaveError,
		}),
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
			return (attachment) => {
				if (attachment.extendedData.fileid) {
					return generateUrl('/f/' + attachment.extendedData.fileid)
				}
				return generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
			}
		},
		attachmentPreview() {
			return (attachment) => (attachment.extendedData.fileid
				? generateUrl(`/core/preview?fileId=${attachment.extendedData.fileid}&x=600&y=600&a=true`)
				: generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`))
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
		renderedDescription() {
			return markdownIt.render(this.card.description || '')
		},
		hasDescription() {
			return this.card?.description?.trim?.() !== ''
		},
	},
	mounted() {
		this.setupEditor()
	},
	async beforeDestroy() {
		await this.destroyEditor()
	},
	methods: {
		async setupEditor() {
			await this.destroyEditor()
			this.descriptionLastEdit = 0
			this.description = this.card.description
			this.editor = await window.OCA.Text.createEditor({
				el: this.$refs.editor,
				content: this.card.description,
				readOnly: !this.canEdit,
				onLoaded: () => {
					this.descriptionLastEdit = 0
				},
				onUpdate: ({ markdown }) => {
					if (this.description === markdown) {
						return
					}
					this.description = markdown
					this.updateDescription()
				},
				onFileInsert: () => {
					this.showAttachmentModal()
				},
			})

		},
		async destroyEditor() {
			await this.saveDescription()
			this?.editor?.destroy()
		},
		addKeyListeners() {
			this.$refs.markdownEditor.easymde.codemirror.on('keydown', (a, b) => {
				if (this.keyExitState === 0 && (b.key === 'Meta' || b.key === 'Alt')) {
					this.keyExitState = 1
				}
				if (this.keyExitState === 1 && b.key === 'Enter') {
					this.keyExitState = 0
					this.$refs.markdownEditor.easymde.codemirror.off('keydown', undefined)
					this.$refs.markdownEditor.easymde.codemirror.off('keyup', undefined)
					this.hideEditor()
				}
			})
			this.$refs.markdownEditor.easymde.codemirror.on('keyup', (a, b) => {
				if (b.key === 'Meta' || b.key === 'Control') {
					this.keyExitState = 0
				}

			})
		},
		showEditor() {
			if (!this.canEdit) {
				return
			}
			this.descriptionEditing = true
			this.description = this.card.description

		},
		hideEditor() {
			this.$refs.markdownEditor.easymde.codemirror.off('keydown', undefined)
			this.$refs.markdownEditor.easymde.codemirror.off('keyup', undefined)
			this.descriptionEditing = false
		},
		showAttachmentModal() {
			this.modalShow = true
		},
		addAttachment(attachment) {
			const asImage = (attachment.type === 'file' && attachment.extendedData.hasPreview) || attachment.extendedData.mimetype.includes('image')
			// We need to strip those as text does not support rtl yet, so we cannot insert them separately
			const stripRTLO = (text) => text.replaceAll('\u202e', '')
			const fileName = stripRTLO(attachment.extendedData.info.filename) + '.' + stripRTLO(attachment.extendedData.info.extension)
			if (this.editor) {
				this.editor.insertAtCursor(
					asImage
						? `<a href="${this.attachmentPreview(attachment)}"><img src="${this.attachmentPreview(attachment)}" alt="${attachment.data}" /></a>`
						: `<a href="${this.attachmentPreview(attachment)}">${fileName}</a>`,
				)
				return
			} else {
				const attachmentString = (asImage ? '!' : '') + '[📎 ' + fileName + '](' + this.attachmentPreview(attachment) + ')'
				const descString = this.$refs.markdownEditor.easymde.value()
				const newContent = descString + '\n' + attachmentString
				this.$refs.markdownEditor.easymde.value(newContent)
				this.description = newContent
			}
			this.updateDescription()
			this.modalShow = false
		},
		clickedPreview(e) {
			if (e.target.getAttribute('type') === 'checkbox') {
				const clickedIndex = [...document.querySelector('#description-preview').querySelectorAll('input')].findIndex((li) => li.id === e.target.id)
				const reg = /\[(X|\s|_|-)\]/ig
				let nth = 0
				const updatedDescription = this.card.description.replace(reg, (match, i, original) => {
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
				this.$emit('change', updatedDescription)
			}
		},
		async saveDescription() {
			if (this.descriptionLastEdit === 0 || this.descriptionSaving) {
				return
			}
			this.descriptionSaving = true
			try {
				if (this.card.id !== undefined) {
					await this.$store.dispatch('updateCardDesc', { ...this.card, description: this.description })
				}
				this.$emit('change', this.description)
				this.descriptionLastEdit = 0
				this.$store.commit('setHasCardSaveError', false)
			} catch (e) {
				this.$store.commit('setHasCardSaveError', true)
				showWarning(t('deck', 'Could not save description'), { timeout: 2500 })
				console.error(e)

				// Retry of network error
				if (['ERR_NETWORK', 'ETIMEDOUT'].includes(e.code)) {
					this.setSaveTimeout()
				}
			} finally {
				this.descriptionSaving = false
			}
		},
		updateDescription() {
			this.descriptionLastEdit = Date.now()
			this.setSaveTimeout()
		},
		setSaveTimeout() {
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(async () => {
				await this.saveDescription()
			}, 2500)
		},
	},
}
</script>
<style lang="scss" scoped>

.modal__content {
	width: 25vw;
	min-width: 250px;
	min-height: 120px;
	margin: 20px;
	padding-bottom: 20px;
	display: flex;
	flex-direction: column;

	&:deep(.attachment-list) {
		flex-shrink: 1;
	}
}

.placeholder {
	color: var(--color-text-maxcontrast);
	padding: 2px;
}

#description-preview {
	min-height: 100px;
	width: auto;
	overflow-x: auto;

	&:deep {
		/* stylelint-disable-next-line no-invalid-position-at-import-rule */
		@import './../../css/markdown';
	}

	&:deep(input) {
		height: auto;
	}

	&:deep(a) {
		text-decoration: underline;
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

.description__text :deep(.ProseMirror) {
	padding-bottom: var(--default-clickable-area);
}

</style>
<style>
@import '~easymde/dist/easymde.min.css';

.vue-easymde, .CodeMirror {
	border: none;
	margin: 0;
	padding: 0;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	width: 100%;
}

.CodeMirror-placeholder {
	color: var(--color-text-maxcontrast);
}

.CodeMirror-cursor {
	border-left: 1px solid var(--color-main-text);
}

.CodeMirror-selected,
.CodeMirror-line::selection, .CodeMirror-line>span::selection, .CodeMirror-line>span>span::selection {
	background: var(--color-primary-element) !important;
	color: var(--color-primary-element-text) !important;
}

.editor-preview,
.editor-statusbar {
	display: none;
}

#app-sidebar .app-sidebar-header__desc h4 {
	font-size: 12px !important;
}

.vue-easymde .cm-s-easymde .cm-link {
	color: var(--color-main-text);
}

.vue-easymde .cm-s-easymde .cm-string.cm-url,
.vue-easymde .cm-s-easymde .cm-formatting.cm-link,
.vue-easymde .cm-s-easymde .cm-formatting.cm-url,
.vue-easymde .cm-s-easymde .cm-formatting.cm-image {
	color: var(--color-text-maxcontrast);
}

.app-sidebar__tab .description__text .text-menubar {
	top: -10px !important;
	z-index: 1;
}

.modal__card .description__text .text-menubar {
	top: 0;
}
</style>
