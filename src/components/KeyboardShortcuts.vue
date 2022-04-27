<template>
	<!--  :style="{top:cardTop, left:cardLeft}" -->
	<div v-if="card && selector"
		ref="shortcutModal"
		v-click-outside="close"
		class="keyboard-shortcuts__modal"
		tabindex="0"
		@keydown.esc="close">
		<CardItem :card="card" />
		<DueDateSelector v-if="selector === 'due-date'" :card="card" :can-edit="true" />
		<TagSelector v-if="selector === 'tag'" :card="card" :can-edit="true" />
		<AssignmentSelector v-if="selector === 'assignment'" :card="card" :can-edit="true" />
	</div>
</template>
<script>
import DueDateSelector from './card/DueDateSelector.vue'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { mapState } from 'vuex'
import TagSelector from './card/TagSelector.vue'
import AssignmentSelector from './card/AssignmentSelector.vue'
import CardItem from './cards/CardItem.vue'

export default {
	name: 'KeyboardShortcuts',
	components: {
		DueDateSelector,
		TagSelector,
		AssignmentSelector,
		CardItem,
	},
	data() {
		return {
			card: null,
			cardTop: null,
			cardLeft: null,
			selector: null,
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard,
		}),
	},
	created() {
		document.addEventListener('keydown', this.onKeydown)
		subscribe('deck:card:show-assignment-selector', this.handleShowAssignemnt)
		subscribe('deck:card:show-due-date-selector', this.handleShowDueDate)
		subscribe('deck:card:show-label-selector', this.handleShowLabel)
	},
	destroyed() {
		document.removeEventListener('keydown', this.onKeydown)
		unsubscribe('deck:card:show-assignment-selector', this.handleShowAssignemnt)
		unsubscribe('deck:card:show-due-date-selector', this.handleShowDueDate)
		unsubscribe('deck:card:show-label-selector', this.handleShowLabel)
	},
	methods: {
		onKeydown(key) {
			if (OCP.Accessibility.disableKeyboardShortcuts()) {
				return
			}

			if (['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(key.target.tagName) || key.target.isContentEditable) {
				return
			}

			// Global shortcuts (not board specific)
			if ((key.metaKey || key.ctrlKey) && key.code === 'KeyF') {
				const searchInput = document.getElementById('deck-search-input')
				if (searchInput === document.activeElement) {
					return false
				}

				document.getElementById('deck-search-input').focus()
				key.preventDefault()
				return true
			}
			if (key.code === 'Minus') {
				emit('deck:global:toggle-help-dialog')
				return
			}

			if (this.$store.state.shortcutLock || key.shiftKey || key.ctrlKey || key.altKey || key.metaKey) {
				return
			}

			if (this.$route.name === 'card' && key.code === 'Escape') {
				this.$router.push({ name: 'board' })
				return
			}

			// Board specific shortcuts
			if (!this.board) {
				return
			}

			switch (key.code) {
			case 'KeyN':
				emit('deck:board:show-new-card', this.board.id)
				break
			case 'KeyF':
				emit('deck:board:toggle-filter-popover', this.board.id)
				break
			case 'KeyX':
				emit('deck:board:clear-filter', this.board.id)
				break
			case 'KeyQ':
				emit('deck:board:toggle-filter-by-me', this.board.id)
				break
			case 'ArrowDown':
				this.keyboardFocusDown()
				break
			case 'ArrowUp':
				this.keyboardFocusUp()
				break
			case 'ArrowLeft':
				this.keyboardFocusLeft()
				break
			case 'ArrowRight':
				this.keyboardFocusRight()
				break
			default:
				return
			}

			key.preventDefault()
		},
		keyboardFocusDown() {
			const activeCard = document.activeElement.closest('.card')
			const cards = document.querySelectorAll('.card')
			const stacks = document.querySelectorAll('.stack')
			const index = Array.from(cards).findIndex(card => card === activeCard)
			if (index === -1) {
				cards[0]?.focus()
				return
			}

			const currentStack = Array.from(stacks).find(stack => stack.contains(document.activeElement))
			const currentStackCards = currentStack.querySelectorAll('.card')
			const currentStackIndex = Array.from(currentStackCards).findIndex(card => card === document.activeElement)

			if (currentStackIndex === currentStackCards.length - 1) {
				return
			}

			cards[index + 1]?.focus()
			cards[index + 1]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
		},

		keyboardFocusUp() {
			const activeCard = document.activeElement.closest('.card')
			const cards = document.querySelectorAll('.card')
			const stacks = document.querySelectorAll('.stack')
			const index = Array.from(cards).findIndex(card => card === activeCard)
			if (index === -1) {
				cards[0]?.focus()
				return
			}

			const currentStack = Array.from(stacks).find(stack => stack.contains(document.activeElement))
			const currentStackCards = currentStack.querySelectorAll('.card')
			const currentStackIndex = Array.from(currentStackCards).findIndex(card => card === document.activeElement)

			if (currentStackIndex === 0) {
				return
			}

			cards[index - 1]?.focus()
			cards[index - 1]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
		},
		keyboardFocusLeft() {
			const activeCard = document.activeElement.closest('.card')
			const stacks = document.querySelectorAll('.stack')
			const currentStackIndex = Array.from(stacks).findIndex(stack => stack.contains(activeCard))

			if (!currentStackIndex === 0) {
				return
			}

			const nextStack = stacks[currentStackIndex - 1] ?? stacks[0]

			const currentCardTopOffset = document.activeElement.getBoundingClientRect().top

			// iterate over all next stack cards and remember the one with the smallest offset
			const nextStackCards = nextStack.querySelectorAll('.card')
			let nextCard = null
			for (const card of nextStackCards) {
				const cardTopOffset = card.getBoundingClientRect().bottom
				if (cardTopOffset < currentCardTopOffset) {
					continue
				}

				nextCard = card
				break
			}

			if (!nextCard) {
				nextCard = nextStackCards[nextStackCards.length - 1]
			}

			nextCard?.focus()
			nextCard?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
		},
		keyboardFocusRight() {
			const activeCard = document.activeElement.closest('.card')
			const stacks = document.querySelectorAll('.stack')
			const currentStackIndex = Array.from(stacks).findIndex(stack => stack.contains(activeCard))

			if (currentStackIndex === stacks.length - 1) {
				return
			}

			const nextStack = stacks[currentStackIndex + 1]

			const currentCardTopOffset = document.activeElement.getBoundingClientRect().top

			// iterate over all next stack cards and remember the one with the smallest offset
			const nextStackCards = nextStack.querySelectorAll('.card')
			let nextCard = null
			for (const card of nextStackCards) {
				const cardTopOffset = card.getBoundingClientRect().bottom
				if (cardTopOffset < currentCardTopOffset) {
					continue
				}

				nextCard = card
				break
			}

			if (!nextCard) {
				nextCard = nextStackCards[nextStackCards.length - 1]
			}

			nextCard?.focus()
			nextCard?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
		},
		handleShowDueDate({ card, element }) {
			// this.cardTop = element.getBoundingClientRect().top + 'px'
			// this.cardLeft = element.getBoundingClientRect().left + 'px'
			this.card = card
			this.selector = 'due-date'
			this.$refs.shortcutModal?.focus()
		},
		handleShowAssignemnt({ card, element }) {
			// this.cardTop = element.getBoundingClientRect().top + 'px'
			// this.cardLeft = element.getBoundingClientRect().left + 'px'
			this.card = card
			this.selector = 'assignment'
			this.$refs.shortcutModal?.focus()
		},
		handleShowLabel({ card, element }) {
			// this.cardTop = element.getBoundingClientRect().top + 'px'
			// this.cardLeft = element.getBoundingClientRect().left + 'px'
			this.card = card
			this.selector = 'tag'
			this.$refs.shortcutModal?.focus()
		},
		close() {
			this.card = null
			this.selector = null
		},
	},
}
</script>
<style lang="scss" scoped>
.keyboard-shortcuts__modal {
	position: fixed;
	z-index: 9999;
	box-shadow: 0 0 100px 30px rgba(0, 0, 0, 0.5);
	max-width: 500px;
	bottom: 32px;
	left: 50%;
	transform: translateX(-50%);
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-rounded);
	padding: 24px 32px;
	width: 100%;
	border: 2px solid var(--color-border-maxcontrast);
}
</style>
