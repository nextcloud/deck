<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :open.sync="showModal"
		:name="t('deck', 'Add multiple cards')"
		size="normal"
		@update:open="onClose">
		<div class="bulk-add">
			<NcSelect v-model="selectedStack"
				:input-label="t('deck', 'Select a list')"
				:placeholder="t('deck', 'Select a list')"
				:options="stacks"
				:clearable="false"
				label="title"
				@option:selected="focusTitleInput" />

			<form class="bulk-add__form" @submit.prevent="createCard">
				<NcTextField ref="titleInput"
					:value.sync="cardTitle"
					:label="t('deck', 'Card title')"
					:placeholder="t('deck', 'Enter a card title and press Enter')"
					:disabled="isCreating || !selectedStack"
					@keydown.enter.prevent="createCard" />
				<NcButton type="secondary"
					:disabled="!canCreate"
					@click="createCard">
					{{ t('deck', 'Add') }}
					<template #icon>
						<PlusIcon :size="20" />
					</template>
				</NcButton>
			</form>
		</div>

		<template #actions>
			<NcButton type="primary" @click="done">
				{{ t('deck', 'Done') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcSelect, NcTextField } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

export default {
	name: 'BulkAddCardsModal',
	components: {
		NcDialog,
		NcButton,
		NcSelect,
		NcTextField,
		PlusIcon,
	},
	props: {
		board: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			showModal: true,
			selectedStack: null,
			cardTitle: '',
			isCreating: false,
		}
	},
	computed: {
		stacks() {
			return this.$store.getters.stacksByBoard(this.board.id)
		},
		canCreate() {
			return this.selectedStack && this.cardTitle.trim() !== '' && !this.isCreating
		},
	},
	mounted() {
		if (this.stacks.length > 0) {
			this.selectedStack = this.stacks[0]
		}
		this.focusTitleInput()
	},
	methods: {
		focusTitleInput() {
			this.$nextTick(() => {
				this.$refs.titleInput?.$el?.querySelector('input')?.focus()
			})
		},
		async createCard() {
			if (!this.canCreate) {
				return
			}
			this.isCreating = true
			try {
				await this.$store.dispatch('addCard', {
					title: this.cardTitle.trim(),
					stackId: this.selectedStack.id,
					boardId: this.board.id,
				})
				this.cardTitle = ''
				this.focusTitleInput()
			} catch (err) {
				showError(t('deck', 'Could not create card'))
			} finally {
				this.isCreating = false
			}
		},
		done() {
			this.showModal = false
			this.onClose()
		},
		onClose() {
			this.$emit('close')
		},
	},
}
</script>

<style lang="scss" scoped>
.bulk-add {
	display: flex;
	flex-direction: column;
	gap: 12px;

	&__form {
		display: flex;
		gap: 8px;
		align-items: flex-end;

		.button-vue {
			flex-shrink: 0;
		}
	}
}
</style>
