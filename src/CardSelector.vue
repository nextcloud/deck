<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal class="card-selector" @close="close">
		<div id="modal-inner" :class="{ 'icon-loading': loading }">
			<h3>{{ title }}</h3>
			<NcSelect v-model="selectedBoard"
				:placeholder="t('deck', 'Select a board')"
				:options="boards"
				:disabled="loading"
				label="title"
				@option:selected="fetchCardsFromBoard">
				<template #selected-option="props">
					<span>
						<span :style="{ 'backgroundColor': '#' + props.color }" class="board-bullet" />
						<span>{{ props.title }}</span>
					</span>
				</template>
				<template #option="props">
					<span>
						<span :style="{ 'backgroundColor': '#' + props.color }" class="board-bullet" />
						<span>{{ props.title }}</span>
					</span>
				</template>
			</NcSelect>

			<NcSelect v-model="selectedCard"
				:placeholder="t('deck', 'Select a card')"
				:options="cardsFromBoard"
				:disabled="loading || selectedBoard === ''"
				label="title" />

			<button :disabled="!isBoardAndStackChoosen" class="primary" @click="select">
				{{ action }}
			</button>
			<button @click="close">
				{{ t('deck', 'Cancel') }}
			</button>
		</div>
	</NcModal>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { NcModal, NcSelect } from '@nextcloud/vue'
import axios from '@nextcloud/axios'

export default {
	name: 'CardSelector',
	components: {
		NcModal,
		NcSelect,
	},
	props: {
		title: {
			type: String,
			default: t('deck', 'Select the card to link to a project'),
		},
		action: {
			type: String,
			default: t('deck', 'Link to card'),
		},
	},
	data() {
		return {
			boards: [],
			selectedBoard: '',
			cardsFromBoard: [],
			selectedCard: '',
			loading: true,
		}
	},
	computed: {
		isBoardAndStackChoosen() {
			return !(this.selectedBoard === '' || this.selectedCard === '')
		},
	},
	beforeMount() {
		this.fetchBoards()
	},
	methods: {
		fetchBoards() {
			axios.get(generateUrl('/apps/deck/boards')).then((response) => {
				this.boards = response.data
				this.loading = false
			})
		},
		async fetchCardsFromBoard(board) {
			try {
				this.cardsFromBoard = []
				const url = generateUrl('/apps/deck/stacks/' + board.id)
				const response = await axios.get(url)
				response.data.forEach(stack => {
					this.cardsFromBoard.push(...stack.cards)
				})
			} catch (err) {
				return err
			}

		},
		close() {
			this.$root.$emit('close')
		},
		select() {
			this.$root.$emit('select', this.selectedCard.id)

		},
	},

}
</script>

<style scoped>
	#modal-inner {
		width: 90vw;
		max-width: 400px;
		padding: 20px;
		height: 200px;
	}

	.multiselect {
		width: 100%;
		margin-bottom: 10px;
	}

	ul {
		min-height: 100px;
	}

	li {
		padding: 6px;
		border: 1px solid transparent;
	}

	li:hover, li:focus {
		background-color: var(--color-background-dark);
	}

	.board-bullet {
		display: inline-block;
		width: 12px;
		height: 12px;
		border: none;
		border-radius: 50%;
		cursor: pointer;
	}

	button {
		float: right;
	}

	.card-selector:deep(.modal-container) {
		overflow: visible !important;
	}
</style>
