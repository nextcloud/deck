<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-click-outside="close" class="stack__card-add">
		<NcButton v-if="!visible"
			data-cy="action:add-card"
			class="stack__card-add-button"
			type="tertiary"
			:wide="true"
			@click.stop="visible = true">
			<template #icon>
				<PlusIcon :size="20" />
			</template>
			{{ t('deck', 'Add card') }}
		</NcButton>
		<form v-else
			:class="{ 'icon-loading-small': creating }"
			@submit.prevent.stop="addCard">
			<label for="new-stack-input-main" class="hidden-visually">{{ t('deck', 'Add a new card') }}</label>
			<input id="new-stack-input-main"
				ref="newCardInput"
				v-model="title"
				type="text"
				class="no-close"
				:disabled="creating"
				:placeholder="t('deck', 'Card name')"
				required
				pattern=".*\S+.*"
				@focus="$store.dispatch('toggleShortcutLock', true)"
				@keydown.esc.stop="visible = false">
			<input v-show="!creating"
				class="icon-confirm"
				type="submit"
				value="">
		</form>
	</div>
</template>

<script>
import ClickOutside from 'vue-click-outside'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { mapActions } from 'pinia'

import { useCardStore } from '../../stores/card.js'

export default {
	name: 'StackCardAdd',
	components: {
		NcButton,
		PlusIcon,
	},
	directives: {
		ClickOutside,
	},
	props: {
		stack: {
			type: Object,
			required: true,
		},
		addAtTop: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			title: '',
			visible: false,
			creating: false,
		}
	},
	computed: {
		cardDetailsInModal() {
			return this.$store.getters.config('cardDetailsInModal')
		},
	},
	watch: {
		visible(newValue) {
			if (!newValue) {
				this.$store.dispatch('toggleShortcutLock', false)
				return
			}

			this.$nextTick(() => this.$refs.newCardInput.focus())
		},
	},
	methods: {
		...mapActions(useCardStore, {
			addCardInStore: 'addCard',
		}),
		close() {
			this.visible = false
		},
		async addCard() {
			this.creating = true
			this.$emit('creating')
			try {
				const newCard = await this.addCardInStore({
					title: this.title,
					stackId: this.stack.id,
					boardId: this.stack.boardId,
					// Without an order the API appends the card to the end of the stack
					...(this.addAtTop ? { order: 0 } : {}),
				})
				this.title = ''
				this.$emit('created', newCard)
				if (!this.cardDetailsInModal) {
					this.$router.push({ name: 'card', params: { cardId: newCard.id } })
				}
			} catch (error) {
				showError('Could not create card: ' + error.response.data.message)
			} finally {
				this.creating = false
				// Disabling the input while creating drops the focus
				this.$nextTick(() => this.$refs.newCardInput?.focus())
			}
		},
	},
}
</script>

<style lang="scss" scoped>
	@import './../../css/variables.scss';

	.stack__card-add {
		flex-shrink: 0;
		z-index: 100;
		display: flex;
		background-color: var(--color-main-background);
		position: relative;

		.stack--add-card-at-top & {
			padding-top: $stack-gap;

			&:after {
				content: '';
				display: block;
				position: absolute;
				width: 100%;
				height: $stack-gap;
				bottom: 0;
				z-index: 99;
				pointer-events: none;
				background-image: linear-gradient(180deg, var(--color-main-background) 0%, transparent 100%);
				transform: translateY(100%);
			}
		}

		.stack--add-card-at-bottom & {
			padding-bottom: $stack-gap;
		}

		// Smooth fade out of the cards next to the control
		&:before {
			content: '';
			display: block;
			position: absolute;
			width: 100%;
			height: $stack-gap;
			z-index: 99;
			transition: bottom var(--animation-slow);
			background-image: linear-gradient(0deg, var(--color-main-background) 0%, transparent 100%);
			transform: translateY(-100%);
		}

		:deep(.stack__card-add-button.button-vue) {
			--button-size: var(--stack-card-add-control-height);
			color: var(--color-text-maxcontrast);

			&:hover:not(:disabled),
			&:focus-visible {
				color: var(--color-main-text);
			}
		}

		form {
			display: flex;
			width: 100%;
			height: var(--stack-card-add-control-height);
			box-sizing: border-box;
			border: 2px solid var(--color-border-maxcontrast);
			border-radius: var(--border-radius-large);
			overflow: hidden;
			padding: 2px;
		}

		&.icon-loading-small:after,
		&.icon-loading-small-dark:after {
			margin-inline-start: calc(50% - 25px);
		}

		input[type=text] {
			flex-grow: 1;
			padding-inline-end: 16px;
		}

		input {
			border: none;
			margin: 0;
		}
	}
</style>
