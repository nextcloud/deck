<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="modal-scroller">
		<div v-if="!creating && !created" id="modal-inner" :class="{ 'icon-loading': loading }">
			<h2>{{ t('deck', 'Create a new card') }}</h2>
			<input ref="cardTitleInput"
				v-model="card.title"
				v-focus
				type="text"
				class="card-title"
				:placeholder="t('deck', 'Card title')"
				:disabled="loading">

			<div class="row">
				<div class="col selector-wrapper">
					<div class="selector-wrapper--icon">
						<DeckIcon :size="20" />
					</div>
					<NcSelect v-model="selectedBoard"
						:placeholder="t('deck', 'Select a board')"
						:options="boards"
						:disabled="loading"
						label="title"
						class="selector-wrapper--selector multiselect-board"
						@option:selected="onSelectBoard">
						<template #selected-option="option">
							<span>
								<span :style="{ 'backgroundColor': '#' + option.color }" class="board-bullet" />
								<span data-cy="board-select-title">{{ option.title }}</span>
							</span>
						</template>
						<template #option="option">
							<span>
								<span :style="{ 'backgroundColor': '#' + option.color }" class="board-bullet" />
								<span data-cy="board-select-title">{{ option.title }}</span>
							</span>
						</template>
					</NcSelect>
				</div>

				<div class="col selector-wrapper">
					<div class="selector-wrapper--icon">
						<FormatColumnsIcon :size="20" />
					</div>
					<NcSelect v-model="selectedStack"
						:placeholder="t('deck', 'Select a list')"
						:options="stacksFromBoard"
						:max-height="100"
						:disabled="loading || !selectedBoard"
						class="selector-wrapper--selector multiselect-list"
						label="title"
						@option:selected="onSelectStack" />
				</div>
			</div>

			<TagSelector :card="card"
				:labels="labels"
				:disabled="loading || !selectedBoard"
				@select="onSelectLabel"
				@remove="onRemoveLabel"
				@newtag="addLabelToBoardAndCard" />

			<AssignmentSelector :card="card"
				:assignables="assignables"
				@select="onSelectUser"
				@remove="onRemoveUser" />

			<DueDateSelector :card="card"
				:can-edit="!loading && !!selectedBoard"
				@change="updateCardDue"
				@input="updateCardDue" />

			<Description :key="card.id"
				:card="card"
				@change="descriptionChanged" />

			<div class="modal-buttons">
				<NcButton @click="close">
					{{ t('deck', 'Cancel') }}
				</NcButton>
				<NcButton :disabled="loading || !isBoardAndStackChoosen"
					type="primary"
					@click="createCard">
					{{ t('deck', 'Create card') }}
				</NcButton>
			</div>
		</div>
		<div v-else id="modal-inner">
			<NcEmptyContent v-if="creating">
				<template #icon>
					<NcLoadingIcon />
				</template>
				<template #name>
					{{ t('deck', 'Creating the new card …') }}
				</template>
			</NcEmptyContent>
			<NcEmptyContent v-else-if="created && showCreatedNotice">
				<template #icon>
					<CardPlusOutline />
				</template>
				<template #name>
					{{ t('deck', 'Card "{card}" was added to "{board}"', { card: card.title, board: selectedBoard.title }) }}
				</template>
				<template #action>
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
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import {
	NcButton,
	NcSelect,
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { CardApi } from '../services/CardApi.js'
import Color from '../mixins/color.js'
import AssignmentSelector from '../components/card/AssignmentSelector.vue'
import TagSelector from '../components/card/TagSelector.vue'
import { BoardApi } from '../services/BoardApi.js'
import DueDateSelector from '../components/card/DueDateSelector.vue'
import Description from '../components/card/Description.vue'
import CardPlusOutline from 'vue-material-design-icons/CardPlusOutline.vue'
import FormatColumnsIcon from 'vue-material-design-icons/FormatColumns.vue'
import DeckIcon from '../components/icons/DeckIcon.vue'
import { showError } from '../helpers/errors.js'

const cardApi = new CardApi()
const apiClient = new BoardApi()

export default {
	name: 'CreateNewCardCustomPicker',
	components: {
		DeckIcon,
		FormatColumnsIcon,
		CardPlusOutline,
		Description,
		DueDateSelector,
		TagSelector,
		AssignmentSelector,
		NcButton,
		NcSelect,
		NcEmptyContent,
		NcLoadingIcon,
	},
	mixins: [Color],
	props: {
		showCreatedNotice: {
			type: Boolean,
			default: false,
		},
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
			card: {
				title: '',
				description: '',
				labels: [],
				assignedUsers: [],
				duedate: null,
			},
			boards: [],
			stacksFromBoard: [],
			labels: [],
			selectedUsers: [],
			loading: true,
			selectedStack: '',
			selectedBoard: '',
			selectedLabels: [],
			boardUsers: [],
			boardAcl: [],
			creating: false,
			created: false,
			newCard: {},
		}
	},
	computed: {
		isBoardAndStackChoosen() {
			return !(this.selectedBoard === '' || this.selectedStack === '')
		},
		assignables() {
			return [
				...this.boardUsers.map((user) => ({ ...user, type: 0 })),
				...this.boardAcl.filter((acl) => acl.type === 1 && typeof acl.participant === 'object').map((group) => ({ ...group.participant, type: 1 })),
				...this.boardAcl.filter((acl) => acl.type === 7 && typeof acl.participant === 'object').map((circle) => ({ ...circle.participant, type: 7 })),
			]
		},
	},
	beforeMount() {
		this.$set(this.card, 'title', this.title)
		this.$set(this.card, 'description', this.description)
		this.fetchBoards()
	},
	mounted() {
		this.$nextTick(() => {
			this.$refs.cardTitleInput.focus()
		})
	},
	methods: {
		fetchBoards() {
			axios.get(generateUrl('/apps/deck/boards')).then((response) => {
				this.boards = response.data.filter((board) => {
					return board?.permissions?.PERMISSION_EDIT && !board?.archived && !board?.deletedAt
				})
				this.loading = false
				this.preSelectBoard()
			})
		},
		async fetchBoardDetails(board) {
			try {
				const url = generateUrl('/apps/deck/boards/' + board.id)
				const response = await axios.get(url)
				this.stacksFromBoard = response.data.stacks
				this.labels = response.data.labels
				this.boardUsers = response.data.users
				this.boardAcl = response.data.acl
				this.preSelectStack()
			} catch (err) {
				return err
			}

		},
		close() {
			this.$emit('cancel')
		},
		async createCard() {
			this.creating = true

			try {
				const response = await cardApi.addCard({
					boardId: this.selectedBoard.id,
					stackId: this.selectedStack.id,
					title: this.card.title,
					description: this.card.description,
					duedate: this.card.duedate,
					labels: this.card.labels.map(label => label.id),
					users: this.card.assignedUsers.map(user => {
						return {
							id: user.uid,
							type: user.type,
						}
					}),
				})
				this.newCard = response
				this.creating = false
				this.created = true
				this.$emit('submit', window.location.protocol + '//' + window.location.host + generateUrl('/apps/deck') + `/card/${this.newCard.id}`)
			} catch (e) {
				this.creating = false
				showError(e)
			}
		},
		onSelectLabel(label) {
			this.card.labels.push(label)
		},
		onRemoveLabel(removedLabel) {
			this.card.labels = this.card.label.filter(label => label.id !== removedLabel.id)
		},
		onSelectUser(user) {
			this.card.assignedUsers.push(user)
		},
		onRemoveUser(removedUser) {
			this.card.assignedUsers = this.card.assignedUsers.filter(user => user.uid !== removedUser.uid)
		},
		async addLabelToBoardAndCard(name) {
			const label = await apiClient.createLabel({
				title: name,
				color: this.randomColor(),
				boardId: this.selectedBoard.id,
			})
			this.card.labels.push(label)
			this.labels.push(label)
		},
		updateCardDue(newValue) {
			this.card.duedate = newValue
		},
		descriptionChanged(newValue) {
			this.card.description = newValue
		},
		openNewCard() {
			window.location = generateUrl('/apps/deck') + `#/board/${this.selectedBoard.id}/card/${this.newCard.id}`
		},
		preSelectBoard() {
			const selectedBoardId = Number(localStorage.getItem('deck.selectedBoardId'))
			const preSelectedBoard = this.boards.find(item => item.id === selectedBoardId)

			if (preSelectedBoard) {
				this.selectedBoard = preSelectedBoard
				this.onSelectBoard(preSelectedBoard)
			}
		},
		preSelectStack() {
			const selectedStackId = Number(localStorage.getItem('deck.selectedStackId'))
			const preSelectedStack = this.stacksFromBoard.find(item => item.id === selectedStackId)

			if (preSelectedStack) {
				this.selectedStack = preSelectedStack
			}
		},
		async onSelectBoard(board) {
			localStorage.setItem('deck.selectedBoardId', board.id)
			this.selectedStack = ''
			await this.fetchBoardDetails(board)
		},
		onSelectStack(stack) {
			localStorage.setItem('deck.selectedStackId', stack.id)
		},
	},

}
</script>

<style lang="scss" scoped>
@import '../css/selector';

.modal-scroller {
	overflow: scroll;
	max-height: calc(80vh - 40px);
	width: calc(100% - 24px);
	padding: 12px;
}

#modal-inner {
	width: auto;
	margin-left: 10px;
	margin-right: 10px;
}

h2 {
	text-align: center;
}

.card-title {
	width: 100%;
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
	position: sticky;
	bottom: 0;
	z-index: 10100;
	gap: 12px;
}

.v-select {
	min-width: auto !important;
}

.empty-content {
	margin-top: 5vh !important;

	&:deep(h2) {
		margin-bottom: 5vh;
	}
}

.row {
	display: flex;
	gap: 12px;

	.col {
		display: flex;
		width: 50%;
	}
}
</style>
