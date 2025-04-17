<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="modalShow" :title="t('deck', 'Move card to another board')" @close="modalShow=false">
		<div class="modal__content">
			<h3>{{ t('deck', 'Move card to another board') }}</h3>
			<NcSelect v-model="selectedBoard"
				:input-label="t('deck', 'Select a board')"
				:placeholder="t('deck', 'Select a board')"
				:options="activeBoards"
				:max-height="100"
				label="title"
				@option:selected="loadStacksFromBoard" />
			<NcSelect v-model="selectedStack"
				:disabled="stacksFromBoard.length === 0"
				:placeholder="stacksFromBoard.length === 0 ? t('deck', 'No lists available') : t('deck', 'Select a list')"
				:input-label="t('deck', 'Select a list')"
				:options="stacksFromBoard"
				:max-height="100"
				data-cy="select-stack"
				label="title" />

			<button :disabled="!isBoardAndStackChoosen" class="primary" @click="moveCard">
				{{ t('deck', 'Move card') }}
			</button>
			<button @click="modalShow=false">
				{{ t('deck', 'Cancel') }}
			</button>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcSelect } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

export default {
	name: 'CardMoveDialog',
	components: { NcModal, NcSelect },
	data() {
		return {
			card: null,
			modalShow: false,
			selectedBoard: '',
			selectedStack: '',
			stacksFromBoard: [],
		}
	},
	computed: {
		activeBoards() {
			return this.$store.getters.boards.filter((item) => item.deletedAt === 0 && item.archived === false)
		},
		isBoardAndStackChoosen() {
			return !(this.selectedBoard === '' || this.selectedStack === '')
		},
	},
	watch: {
		selectedBoard: {
			immediate: true,
			handler() {
				this.selectedStack = ''
				this.stacksFromBoard = []
			},
		},
	},
	mounted() {
		subscribe('deck:card:show-move-dialog', this.openModal)
	},
	destroyed() {
		unsubscribe('deck:card:show-move-dialog', this.openModal)
	},
	methods: {
		openModal(card) {
			this.card = card
			this.modalShow = true
		},
		async loadStacksFromBoard(board) {
			try {
				const url = generateUrl('/apps/deck/stacks/' + board.id)
				const response = await axios.get(url)
				this.stacksFromBoard = response.data
			} catch (err) {
				return err
			}
		},
		async moveCard() {
			this.copiedCard = Object.assign({}, this.card)
			this.copiedCard.stackId = this.selectedStack.id
			this.$store.dispatch('moveCard', this.copiedCard)
			if (parseInt(this.boardId) === parseInt(this.selectedStack.boardId)) {
				await this.$store.commit('addNewCard', { ...this.copiedCard })
			}
			this.modalShow = false
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	min-width: 250px;
	min-height: 120px;
	margin: 20px 20px 100px 20px;

	h3 {
		font-weight: bold;
		text-align: center;
	}

	.select {
		margin-bottom: 12px;
	}
}

.modal__content button {
	float: right;
	margin-top: 50px;
}
</style>
