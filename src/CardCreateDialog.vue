<!--
  - @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
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
	<NcModal class="card-selector" @close="close">
		<div class="modal-scroller">
			<div v-if="!creating && !created" id="modal-inner" :class="{ 'icon-loading': loading }">
				<h3>{{ t('deck', 'Create a new card') }}</h3>
				<NcMultiselect v-model="selectedBoard"
					:placeholder="t('deck', 'Select a board')"
					:options="boards"
					:disabled="loading"
					label="title"
					class="multiselect-board"
					@select="fetchCardsFromBoard">
					<template slot="singleLabel" slot-scope="props">
						<span>
							<span :style="{ 'backgroundColor': '#' + props.option.color }" class="board-bullet" />
							<span>{{ props.option.title }}</span>
						</span>
					</template>
					<template slot="option" slot-scope="props">
						<span>
							<span :style="{ 'backgroundColor': '#' + props.option.color }" class="board-bullet" />
							<span>{{ props.option.title }}</span>
						</span>
					</template>
				</NcMultiselect>

				<NcMultiselect v-model="selectedStack"
					:placeholder="t('deck', 'Select a list')"
					:options="stacksFromBoard"
					:max-height="100"
					:disabled="loading || !selectedBoard"
					class="multiselect-list"
					label="title" />

				<input v-model="pendingTitle"
					type="text"
					:placeholder="t('deck', 'Card title')"
					:disabled="loading || !selectedStack">
				<textarea v-model="pendingDescription" :disabled="loading || !selectedStack" />
				<div class="modal-buttons">
					<button @click="close">
						{{ t('deck', 'Cancel') }}
					</button>
					<button :disabled="loading || !isBoardAndStackChoosen"
						class="primary"
						@click="select">
						{{ action }}
					</button>
				</div>
			</div>
			<div v-else id="modal-inner">
				<NcEmptyContent v-if="creating" icon="icon-loading">
					{{ t('deck', 'Creating the new card …') }}
				</NcEmptyContent>
				<NcEmptyContent v-else-if="created" icon="icon-checkmark">
					{{ t('deck', 'Card "{card}" was added to "{board}"', { card: pendingTitle, board: selectedBoard.title }) }}
					<template #desc>
						<button class="primary" @click="openNewCard">
							{{ t('deck', 'Open card') }}
						</button>
						<button @click="close">
							{{ t('deck', 'Close') }}
						</button>
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import NcModal from '@nextcloud/vue/dist/Components/NcModal'
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent'
import axios from '@nextcloud/axios'
import { CardApi } from './services/CardApi'

const cardApi = new CardApi()

export default {
	name: 'CardCreateDialog',
	components: {
		NcEmptyContent,
		NcModal,
		NcMultiselect,
	},
	props: {
		title: {
			type: String,
			default: '',
		},
		description: {
			type: String,
			default: '',
		},
		action: {
			type: String,
			default: t('deck', 'Create card'),
		},
	},
	data() {
		return {
			boards: [],
			stacksFromBoard: [],
			loading: true,
			pendingTitle: '',
			pendingDescription: '',
			selectedStack: '',
			selectedBoard: '',
			creating: false,
			created: false,
			newCard: null,
		}
	},
	computed: {
		isBoardAndStackChoosen() {
			return !(this.selectedBoard === '' || this.selectedStack === '')
		},
	},
	beforeMount() {
		this.fetchBoards()
	},
	mounted() {
		this.pendingTitle = this.title
		this.pendingDescription = this.description
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
					this.stacksFromBoard.push(stack)
				})
			} catch (err) {
				return err
			}

		},
		close() {
			this.$emit('close')
			this.$root.$emit('close')
		},
		async select() {
			this.creating = true
			const response = await cardApi.addCard({
				boardId: this.selectedBoard.id,
				stackId: this.selectedStack.id,
				title: this.pendingTitle,
				description: this.pendingDescription,
			})
			this.newCard = response
			this.creating = false
			this.created = true
			// We do not emit here since we want to give feedback to the user that the card was created
			// this.$root.$emit('select', createdCard)
		},
		openNewCard() {
			window.location = generateUrl('/apps/deck') + `#/board/${this.selectedBoard.id}/card/${this.newCard.id}`
		},
	},

}
</script>

<style lang="scss" scoped>
	.modal-scroller {
		overflow: scroll;
		max-height: calc(80vh - 40px);
		margin: 10px;
	}

	#modal-inner {
		width: 90vw;
		max-width: 400px;
		padding: 10px;
		min-height: 200px;
	}

	.multiselect-board, .multiselect-list, input, textarea {
		width: 100%;
		margin-bottom: 10px !important;
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

	.modal-buttons {
		display: flex;
		justify-content: flex-end;
	}

	.card-selector::v-deep .modal-container {
		overflow: visible !important;
	}

	.empty-content {
		margin-top: 5vh !important;

		&::v-deep h2 {
			margin-bottom: 5vh;
		}
	}
</style>
