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
import { NcDialog, NcSelect, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { mapGetters } from 'vuex'

export default {
	name: 'StackMoveDialog',
	components: { NcDialog, NcSelect, NcButton },
	data() {
		return {
			stack: null,
			modalShow: false,
			selectedBoard: '',
			loading: false,
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
	},
	methods: {
		openModal(stack) {
			this.stack = stack
			this.selectedBoard = this.boardById(stack.boardId) ?? ''
			this.modalShow = true
		},
		async moveStack() {
			await this.submit('moveStack', t('deck', 'Could not move the list'))
		},
		async cloneStack() {
			await this.submit('cloneStack', t('deck', 'Could not copy the list'))
		},
		async submit(action, errorMessage) {
			if (!this.canSubmit) {
				return
			}
			this.loading = true
			try {
				await this.$store.dispatch(action, {
					stackId: this.stack.id,
					targetBoardId: this.selectedBoard.id,
				})
				this.modalShow = false
			} catch (err) {
				showError(errorMessage)
				console.error(err)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	.select {
		margin-bottom: 12px;
	}
}
</style>
