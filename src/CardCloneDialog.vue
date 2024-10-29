<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="modalShow" :title="t('deck', 'Clone card')" @close="modalShow = false">
		<div class="modal__content">
			<h3>{{ t('deck', 'Clone card to another board') }}</h3>
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
				label="title" />

			<button :disabled="!isBoardAndStackChoosen" class="primary" @click="moveCard">
				{{ t('deck', 'Clone card') }}
			</button>
			<button @click="modalShow = false">
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
import { mapGetters, mapState } from 'vuex'

export default {
	name: 'CardCloneDialog',
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
		...mapGetters([
			'boards',
			'stackById',
			'boardById',
		]),
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		activeBoards() {
			return this.$store.getters.boards.filter((item) => item.deletedAt === 0 && item.archived === false)
		},
		isBoardAndStackChoosen() {
			return !(this.selectedBoard === '' || this.selectedStack === '')
		},
	},
	mounted() {
		subscribe('deck:card:show-clone-dialog', this.openModal)
	},
	destroyed() {
		unsubscribe('deck:card:show-clone-dialog', this.openModal)
	},
	methods: {
		openModal(card) {
			this.card = card
			this.selectedStack = this.stackById(this.card.stackId)
			this.selectedBoard = this.boardById(this.selectedStack.boardId)
			this.loadStacksFromBoard(this.selectedBoard)
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
			this.$store.dispatch('addCard', this.copiedCard)
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
