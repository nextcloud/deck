<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<h5>{{ t('deck', 'Deleted lists') }}</h5>
		<ul>
			<li v-for="deletedStack in deletedStacks" :key="deletedStack.id">
				<span class="icon icon-deck" />
				<div class="title" dir="auto">
					<span>{{ deletedStack.title }}</span>
					<span class="timestamp">{{ relativeDate(deletedStack.deletedAt*1000) }}</span>
				</div>
				<button :title="t('settings', 'Undo')"
					class="app-navigation-entry-deleted-button icon-history"
					@click="stackUndoDelete(deletedStack)" />
			</li>
		</ul>

		<h5>{{ t('deck', 'Deleted cards') }}</h5>
		<ul>
			<li v-for="deletedCard in deletedCards" :key="deletedCard.id">
				<div class="icon icon-deck" />
				<div class="title" dir="auto">
					<span>{{ deletedCard.title }}</span>
					<span class="timestamp">{{ relativeDate(deletedCard.deletedAt*1000) }}</span>
				</div>
				<button :title="t('settings', 'Undo')"
					class="app-navigation-entry-deleted-button icon-history"
					@click="cardUndoDelete(deletedCard)" />
			</li>
		</ul>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import relativeDate from '../../mixins/relativeDate.js'

export default {
	name: 'DeletedTabSidebar',
	mixins: [relativeDate],
	props: {
		board: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			isLoading: false,
			copiedDeletedStack: null,
			copiedDeletedCard: null,
		}
	},
	computed: {
		...mapState({
			deletedStacks: state => [...state.trashbin.deletedStacks].sort((a, b) => (a.deletedAt > b.deletedAt) ? -1 : 1),
			deletedCards: state => [...state.trashbin.deletedCards].sort((a, b) => (a.deletedAt > b.deletedAt) ? -1 : 1),
		}),

	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			this.isLoading = true
			await this.$store.dispatch('fetchDeletedItems', this.board.id)
			this.isLoading = false
		},
		stackUndoDelete(deletedStack) {
			const copiedDeletedStack = Object.assign({}, deletedStack)
			copiedDeletedStack.deletedAt = 0
			this.$store.dispatch('stackUndoDelete', copiedDeletedStack)
		},
		cardUndoDelete(deletedCard) {
			const copiedDeletedCard = Object.assign({}, deletedCard)
			copiedDeletedCard.deletedAt = 0
			this.$store.dispatch('cardUndoDelete', copiedDeletedCard)
		},
	},
}
</script>

<style scoped lang="scss">
	ul {
		display: block;

		li {
			display: flex;
			&:hover, &:active, &.focus {
				button {
					opacity: 1;
				}
			}
		}

		span {
			display: block;
		}

		* {
			flex-basis: var(--default-clickable-area);
		}

		.title {
			flex-grow: 2;
			padding: 3px 0px;
			> span:not(.timestamp) {
				line-height: 1.2em;
				margin-bottom: 5px;
			}
			.timestamp {
				font-size: 0.9em;
				color: var(--color-text-lighter);
				margin-top: -7px;
			}
		}

		.live-relative-timestamp {
			flex-grow: 1;
		}

		button {
			border: none;
			background-color: transparent;
			opacity: 0.5;
		}
	}
</style>
