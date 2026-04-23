<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<CardDetailEntry :label="t('deck', 'Assign dependent cards')" data-test="dependant-cards-selector">
		<template #icon>
			<ListBoxOutline :size="20" />
		</template>
		<div class="selector-wrapper--selector">
			<ul v-if="selectedDependentCards.length > 0" class="dependency-list">
				<li v-for="dependentCard in selectedDependentCards" :key="dependentCard.id" class="dependency-item">
					<NcButton v-if="isEditable && !dependentCard.done"
						type="button"
						:aria-label="t('deck', 'Mark as done')"
						:title="t('deck', 'Mark as done')"
						@click.stop="changeCardDoneStatus(dependentCard)">
						<template #icon>
							<CircleOutline :size="16" />
						</template>
					</NcButton>
					<NcButton v-if="isEditable && dependentCard.done"
						type="button"
						:aria-label="t('deck', 'Mark as not done')"
						:title="t('deck', 'Mark as not done')"
						@click.stop="changeCardDoneStatus(dependentCard)">
						<template #icon>
							<CheckCircle :size="16" class="done-indicator" />
						</template>
					</NcButton>
					<div class="dependency-link" @click.stop="openCard(dependentCard)">
						<span :class="{ 'dependency-title--done': !!dependentCard.done }">{{ dependentCard.title }}</span>
					</div>
					<NcButton v-if="isEditable"
						type="button"
						variant="tertiary"
						:aria-label="t('deck', 'Remove dependency')"
						:title="t('deck', 'Remove dependency')"
						@click.stop="onRemove(dependentCard)">
						<template #icon>
							<Close :size="20" />
						</template>
					</NcButton>
				</li>
			</ul>

			<NcButton v-if="isEditable && !showSelector"
				class="add-dependency-button"
				type="button"
				variant="tertiary"
				:aria-label="t('deck', 'Add dependent card')"
				@click="openSelector">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('deck', 'Add dependent card') }}
			</NcButton>

			<div v-if="isEditable && showSelector" class="selector-row">
				<NcSelect ref="cardSelector"
					:options="candidateCards"
					:multiple="true"
					:close-on-select="true"
					:aria-label-combobox="t('deck', 'Assign a dependent card…')"
					:placeholder="t('deck', 'Select a dependent card…')"
					label="title"
					track-by="id"
					@option:selected="onSelect">
					<template #option="scope">
						<div class="dependency-option">
							<span>{{ scope.title }}</span>
						</div>
					</template>
				</NcSelect>
				<NcButton type="button"
					variant="tertiary"
					:aria-label="t('deck', 'Cancel')"
					:title="t('deck', 'Cancel')"
					@click="showSelector = false">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</CardDetailEntry>
</template>

<script>
import { defineComponent } from 'vue'
import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import { NcSelect, NcButton } from '@nextcloud/vue'
import ListBoxOutline from 'vue-material-design-icons/ListBoxOutline.vue'
import CircleOutline from 'vue-material-design-icons/CircleOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import CardDetailEntry from './CardDetailEntry.vue'

export default defineComponent({
	name: 'DependentCardsSelector',
	components: {
		CardDetailEntry,
		ListBoxOutline,
		CircleOutline,
		CheckCircle,
		Close,
		Plus,
		NcSelect,
		NcButton,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			showSelector: false,
		}
	},
	computed: {
		...mapState({
			cards: state => state.card.cards,
		}),
		...mapGetters(['cardById', 'stackById']),
		isEditable() {
			return this.canEdit && !this.card?.done && !this.card?.archived
		},
		dependentCardIds() {
			if (!Array.isArray(this.card?.dependentCards)) {
				return []
			}
			return this.card.dependentCards
				.map((id) => parseInt(id, 10))
				.filter((id) => Number.isInteger(id))
		},
		selectedDependentCards() {
			return this.dependentCardIds
				.map((id) => this.cardById(id))
				.filter(Boolean)
		},
		candidateCards() {
			const currentBoardId = this.getCardBoardId(this.card)
			return this.cards
				.filter((candidate) => candidate.id !== this.card?.id)
				.filter((candidate) => !this.dependentCardIds.includes(candidate.id))
				.filter((candidate) => this.getCardBoardId(candidate) === currentBoardId)
				.sort((a, b) => a.order - b.order || a.createdAt - b.createdAt)
		},
	},
	methods: {
		openSelector() {
			this.showSelector = true
			this.$nextTick(() => {
				this.$refs.cardSelector?.$el?.querySelector('input')?.focus()
			})
		},
		onSelect(options) {
			const addedCard = options.find((option) => !this.dependentCardIds.includes(option.id))
			if (addedCard) {
				this.$emit('select', addedCard)
				this.showSelector = false
			}
		},
		onRemove(removedCard) {
			this.$emit('remove', removedCard)
		},
		getCardBoardId(card) {
			if (!card) {
				return null
			}

			if (card.boardId) {
				return card.boardId
			}

			const stack = this.stackById(card.stackId)
			return stack?.boardId ?? null
		},
		openCard(dependentCard) {
			if (!dependentCard?.id) {
				return
			}

			const boardId = this.getCardBoardId(dependentCard)
				?? this.getCardBoardId(this.card)
				?? parseInt(this.$route?.params?.id, 10)

			if (!Number.isInteger(boardId)) {
				return
			}

			if (this.$router) {
				this.$router.push({ name: 'card', params: { id: boardId, cardId: dependentCard.id } }).catch(() => {})
				return
			}

			window.location = generateUrl('/apps/deck') + `#/board/${boardId}/card/${dependentCard.id}`
		},
		changeCardDoneStatus(card) {
			this.$store.dispatch('changeCardDoneStatus', { ...card, done: !card.done })
		},
	},
})
</script>
<style scoped lang="scss">
.dependency-list {
	width: 100%;
	margin: 0;
	padding: 0;
	list-style: none;
}

.dependency-item {
	display: flex;
	justify-content: space-between;
	margin-bottom: 8px;
	gap: 6px;
}

.dependency-link {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	cursor: pointer;
	border: 2px solid var(--color-border-dark);
	box-shadow: none;
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 2) var(--default-grid-baseline);
	padding-top: 0;
	padding-bottom: 0;
	width: 100%;
}

.dependency-link:hover {
	border-color: var(--color-main-text) !important;
}

.dependency-link span {
	cursor: pointer;
}

.done-indicator {
	color: var(--color-element-success, var(--color-success-text));
}

.dependency-title--done {
	text-decoration: line-through;
}

.selector-row {
	display: flex;
	align-items: center;
	gap: 6px;
}
</style>
