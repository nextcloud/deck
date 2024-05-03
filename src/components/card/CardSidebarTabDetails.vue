<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="copiedCard">
		<TagSelector :card="card"
			:labels="currentBoard.labels"
			:disabled="!canEdit"
			@select="addLabelToCard"
			@remove="removeLabelFromCard"
			@newtag="addLabelToBoardAndCard" />

		<AssignmentSelector :card="card"
			:assignables="assignables"
			:can-edit="canEdit"
			@select="assignUserToCard"
			@remove="removeUserFromCard" />

		<DueDateSelector :card="card"
			:can-edit="canEdit"
			@change="updateCardDue"
			@input="debouncedUpdateCardDue" />

		<div v-if="projectsEnabled" class="section-wrapper">
			<CollectionList v-if="card.id"
				:id="`${card.id}`"
				:name="card.title"
				type="deck-card" />
		</div>

		<Description :key="card.id"
			:card="card"
			:can-edit="canEdit"
			show-attachments
			@change="descriptionChanged" />
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import moment from '@nextcloud/moment'
import { loadState } from '@nextcloud/initial-state'

import { CollectionList } from 'nextcloud-vue-collections'
import Color from '../../mixins/color.js'
import {
	getLocale,
} from '@nextcloud/l10n'
import Description from './Description.vue'
import TagSelector from './TagSelector.vue'
import AssignmentSelector from './AssignmentSelector.vue'
import DueDateSelector from './DueDateSelector.vue'
import { debounce } from 'lodash'

export default {
	name: 'CardSidebarTabDetails',
	components: {
		DueDateSelector,
		AssignmentSelector,
		TagSelector,
		Description,
		CollectionList,
	},
	mixins: [Color],
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			addedLabelToCard: null,
			copiedCard: null,
			locale: getLocale(),
			projectsEnabled: loadState('core', 'projects_enabled', false),
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters(['canEdit', 'assignables']),
		cardDetailsInModal: {
			get() {
				return this.$store.getters.config('cardDetailsInModal')
			},
			set(newValue) {
				this.$store.dispatch('setConfig', { cardDetailsInModal: newValue })
			},
		},
		labelsSorted() {
			return [...this.currentBoard.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
	},
	watch: {
		card() {
			this.initialize()
		},
	},
	mounted() {
		this.initialize()
	},
	methods: {
		async descriptionChanged(newDesc) {
			if (newDesc === this.copiedCard.description) {
				return
			}
			this.copiedCard.description = newDesc
		},
		async initialize() {
			if (!this.card) {
				return
			}

			this.copiedCard = JSON.parse(JSON.stringify(this.card))
			localStorage.setItem('deck.selectedBoardId', this.currentBoard.id)
			localStorage.setItem('deck.selectedStackId', this.card.stackId)
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

		updateCardDue(val) {
			this.$store.dispatch('updateCardDue', {
				...this.copiedCard,
				duedate: val ? (new Date(val)).toISOString() : null,
			})
		},

		debouncedUpdateCardDue: debounce(function(val) {
			this.updateCardDue(val)
		}, 500),

		addLabelToCard(newLabel) {
			this.copiedCard.labels.push(newLabel)
			const data = {
				card: this.copiedCard,
				labelId: newLabel.id,
			}
			this.$store.dispatch('addLabel', data)
		},

		async addLabelToBoardAndCard(name) {
			await this.$store.dispatch('addLabelToCurrentBoardAndCard', {
				card: this.copiedCard,
				newLabel: {
					title: name,
					color: this.randomColor(),
				},
			})
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
	},
}
</script>
<style lang="scss" scoped>
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
		display: flex;
		flex-wrap: wrap;

		.remove-due-button{
			margin-top: -2px;
			margin-left: 6px;
		}
	}
}

.button-group {
	width: 100%;
	display: flex;

	button {
		width: 100%;
	}
}

.done {
	display: flex;
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

.section-details:deep(.multiselect__tags-wrap) {
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

.multiselect:deep(.multiselect__tags-wrap) {
	z-index: 2;
}

.multiselect.multiselect--active:deep(.multiselect__tags-wrap) {
	z-index: 0;
}
</style>
