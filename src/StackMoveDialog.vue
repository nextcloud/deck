<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :open.sync="modalShow" :name="t('deck', 'Move/copy list')">
		<div class="modal__content">
			<NcSelect v-model="selectedBoard"
				:input-label="t('deck', 'Select a board')"
				:placeholder="t('deck', 'Select a board')"
				:options="activeBoards"
				:max-height="100"
				data-cy="select-board"
				label="title" />
			<div v-if="loading" class="modal__progress">
				<NcProgressBar :value="progress" size="medium" />
				<span class="modal__progress-label">{{ progressLabel }}</span>
			</div>
		</div>
		<template #actions>
			<NcButton :disabled="!canSubmit" type="secondary" @click="moveStack">
				{{ t('deck', 'Move list') }}
			</NcButton>
			<NcButton :disabled="!canSubmit" type="primary" @click="cloneStack">
				{{ t('deck', 'Copy list') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcDialog, NcSelect, NcButton, NcProgressBar } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { mapGetters } from 'vuex'

export default {
	name: 'StackMoveDialog',
	components: { NcDialog, NcSelect, NcButton, NcProgressBar },
	data() {
		return {
			stack: null,
			modalShow: false,
			selectedBoard: '',
			loading: false,
			progress: 0,
			progressLabel: '',
			progressTimer: null,
		}
	},
	computed: {
		...mapGetters(['boardById']),
		activeBoards() {
			return this.$store.getters.boards.filter((item) => item.deletedAt === 0 && item.archived === false && item.permissions.PERMISSION_MANAGE)
		},
		canSubmit() {
			return !this.loading && this.selectedBoard !== ''
		},
	},
	mounted() {
		subscribe('deck:stack:show-move-dialog', this.openModal)
	},
	destroyed() {
		unsubscribe('deck:stack:show-move-dialog', this.openModal)
		this.stopProgress()
	},
	methods: {
		openModal(stack) {
			this.stack = stack
			this.selectedBoard = this.boardById(stack.boardId) ?? ''
			this.modalShow = true
		},
		async moveStack() {
			await this.submit('moveStack', t('deck', 'Moving the list …'), t('deck', 'Could not move the list'))
		},
		async cloneStack() {
			await this.submit('cloneStack', t('deck', 'Copying the list …'), t('deck', 'Could not copy the list'))
		},
		async submit(action, progressLabel, errorMessage) {
			if (!this.canSubmit) {
				return
			}
			this.loading = true
			this.startProgress(progressLabel)
			try {
				await this.$store.dispatch(action, {
					stackId: this.stack.id,
					targetBoardId: this.selectedBoard.id,
				})
				// Fill the bar before closing so the completion is visible.
				this.progress = 100
				await new Promise((resolve) => setTimeout(resolve, 300))
				this.modalShow = false
			} catch (err) {
				showError(errorMessage)
				console.error(err)
			} finally {
				this.stopProgress()
				this.loading = false
			}
		},
		startProgress(label) {
			this.progressLabel = label
			this.progress = 0
			// The server handles the whole operation in a single request, so
			// real per-card progress is not available; advance the bar
			// asymptotically towards 95% until the request settles.
			this.progressTimer = setInterval(() => {
				if (this.progress < 95) {
					this.progress += (95 - this.progress) * 0.05
				}
			}, 150)
		},
		stopProgress() {
			if (this.progressTimer) {
				clearInterval(this.progressTimer)
				this.progressTimer = null
			}
			this.progress = 0
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	.select {
		margin-bottom: 12px;
	}

	.modal__progress {
		margin-top: 16px;

		.modal__progress-label {
			display: block;
			margin-top: 4px;
			color: var(--color-text-maxcontrast);
			font-size: 13px;
		}
	}
}
</style>
