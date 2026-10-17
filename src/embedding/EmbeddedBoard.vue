<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup>
import { showWarning } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { computed, inject, provide, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcModal from '@nextcloud/vue/components/NcModal'
import CardMoveDialog from '../CardMoveDialog.vue'
import Board from '../components/board/Board.vue'
import CardSidebar from '../components/card/CardSidebar.vue'
import { useCardStore } from '../stores/card.js'
import { useSettingsStore } from '../stores/settings.js'
import { useStackStore } from '../stores/stack.js'

const { boardId } = defineProps({
	boardId: { type: Number, required: true },
})
const emit = defineEmits(['ready', 'error'])
const route = useRoute()
const router = useRouter()
const cards = useCardStore()
const stacks = useStackStore()
const settings = useSettingsStore()
const moveDialog = ref(null)
const embedding = inject('deckEmbedding')
provide('deckEmbedding', {
	...embedding,
	openMoveDialog: (card) => moveDialog.value.openModal(card),
})
const ready = ref(false)
const cardId = computed(() => route.params.cardId ? Number(route.params.cardId) : null)
const currentCard = computed(() => cardId.value ? cards.cardById(cardId.value) : null)

watch(currentCard, (card, previous) => {
	if (!card && previous?.id === cardId.value) {
		closeCard()
	}
})

watch([ready, cardId], async ([loaded, id], _, onCleanup) => {
	let cancelled = false
	onCleanup(() => {
		cancelled = true
	})
	if (!loaded || !id) {
		return
	}
	try {
		if (!cards.cardById(id)) {
			await stacks.loadArchivedStacks(boardId, { preserveExistingCards: true })
		}
		if (!cancelled && !cards.cardById(id)) {
			throw new Error('Card not found in the embedded board')
		}
	} catch (error) {
		if (!cancelled) {
			emit('error', error)
		}
	}
})

function onReady() {
	ready.value = true
	emit('ready')
}

function closeCard() {
	if (settings.hasCardSaveError) {
		showWarning(t('deck', 'Cannot close unsaved card!'))
		return
	}
	return router.push({ name: 'board', params: { id: boardId } })
}
</script>

<template>
	<div class="deck-embedded-board">
		<Board :id="boardId" @ready="onReady" @error="emit('error', $event)" />
		<NcModal
			v-if="ready && currentCard"
			:clearViewDelay="0"
			:closeOnClickOutside="false"
			:name="t('deck', 'Card details')"
			size="large"
			@close="closeCard">
			<div class="modal__content modal__card deck-embedded-board__card">
				<CardSidebar
					:id="cardId"
					:key="cardId"
					:tabId="route.params.tabId || null"
					:tabQuery="route.params.tabQuery || null" />
			</div>
		</NcModal>
		<CardMoveDialog ref="moveDialog" />
	</div>
</template>

<style scoped lang="scss">
.deck-embedded-board {
	height: 100%;
	min-height: 300px;
	min-width: 0;

	&__card {
		width: 100%;
		height: calc(100% - 20px);
		overflow: hidden;
	}
}
</style>
