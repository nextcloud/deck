<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script>
import { translate as t } from '@nextcloud/l10n'
import { defineComponent, inject, nextTick, onBeforeUnmount, onMounted, ref, useHost, watch } from 'vue'
import logger from '../logger.js'

export function parseId(value) {
	if (value === null || value === undefined || !/^[1-9]\d*$/.test(value)) {
		throw new Error('Expected a positive integer ID')
	}
	const id = Number(value)
	if (!Number.isSafeInteger(id)) {
		throw new Error('Expected a safe integer ID')
	}
	return id
}

export default defineComponent({
	name: 'DeckBoardElement',
	props: {
		boardId: { type: String, default: null },
		cardId: { type: String, default: null },
	},

	setup(props) {
		const host = useHost()
		const mountBoard = inject('mountDeckBoard')
		const identifiers = ref({
			boardId: props.boardId,
			cardId: props.cardId,
		})
		const container = ref(null)
		const loading = ref(true)
		const errorMessage = ref(null)
		let controller
		let instance
		let currentBoardId

		function syncAttributes() {
			const boardId = host.getAttribute('board-id')
			const cardId = host.getAttribute('card-id')
			if (identifiers.value.boardId !== boardId || identifiers.value.cardId !== cardId) {
				identifiers.value = { boardId, cardId }
			}
		}

		// Observe final HTML attributes, not transient DOM-property coercions.
		// This also restores observation after Vue 3.5 permanently disconnects.
		const attributeObserver = new MutationObserver(syncAttributes)

		function disposeBoard() {
			controller?.abort()
			controller = null
			instance?.unmount()
			instance = null
		}

		function isActive(current) {
			return !current.signal.aborted && host.isConnected
		}

		function dispatch(name, detail) {
			// Vue's custom-element emits use an array; preserve our object payloads.
			host.dispatchEvent(new CustomEvent(name, { detail, bubbles: true, composed: true }))
		}

		function showError(error) {
			logger.error('Failed to embed Deck board', { error })
			const message = t('deck', 'Could not load the board')
			errorMessage.value = message
			loading.value = false
			dispatch('deck-error', {
				boardId: identifiers.value.boardId,
				status: error.response?.status ?? null,
				message,
			})
		}

		async function renderBoard() {
			if (!host.isConnected) {
				return
			}
			let boardId
			let cardId
			try {
				boardId = parseId(identifiers.value.boardId)
				cardId = identifiers.value.cardId === null ? null : parseId(identifiers.value.cardId)
			} catch (error) {
				disposeBoard()
				showError(error)
				return
			}

			if (instance && currentBoardId === boardId) {
				const current = controller
				try {
					await instance.setCard(cardId)
				} catch (error) {
					if (isActive(current)) {
						dispatch('deck-error', { boardId, message: error.message, status: null })
						logger.error('Failed to navigate embedded Deck board', { error })
					}
				}
				return
			}

			disposeBoard()
			currentBoardId = boardId
			const current = new AbortController()
			controller = current
			loading.value = true
			errorMessage.value = null
			await nextTick()
			if (!isActive(current)) {
				return
			}
			try {
				const mounted = await mountBoard(container.value, {
					boardId,
					cardId,
					signal: current.signal,
					onReady: () => {
						if (isActive(current)) {
							loading.value = false
							dispatch('deck-ready', { boardId })
						}
					},
					onError: (error) => {
						if (isActive(current)) {
							disposeBoard()
							showError(error)
						}
					},
					onNavigate: (detail) => {
						if (isActive(current)) {
							dispatch('deck-navigation', detail)
						}
					},
				})
				if (isActive(current)) {
					instance = mounted
				} else {
					mounted?.unmount()
				}
			} catch (error) {
				if (isActive(current)) {
					disposeBoard()
					showError(error)
				}
			}
		}

		watch(identifiers, renderBoard)
		onMounted(() => {
			attributeObserver.observe(host, { attributes: true, attributeFilter: ['board-id', 'card-id'] })
			syncAttributes()
			renderBoard()
		})
		onBeforeUnmount(() => {
			attributeObserver.disconnect()
			disposeBoard()
		})

		return { container, errorMessage, loading, t }
	},
})
</script>

<template>
	<div class="deck-board-element">
		<div v-if="errorMessage" role="alert">
			{{ errorMessage }}
		</div>
		<template v-else>
			<div v-if="loading" role="status">
				{{ t('deck', 'Loading board') }}
			</div>
			<div ref="container" class="deck-board-component" />
		</template>
	</div>
</template>

<style scoped lang="scss">
.deck-board-element,
.deck-board-component {
	height: 100%;
}
</style>
