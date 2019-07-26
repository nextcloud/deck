<template>
	<div>

		<h3>{{ t('deck', 'Deleted stacks') }}</h3>

		<ul>
			<li v-for="deletedStack in deletedStacks" :key="deletedStack.id">

				<span class="icon icon-deck" />
				<span class="title">{{ deletedStack.title }}</span>
				<button
					:title="t('settings', 'Undo')"
					class="app-navigation-entry-deleted-button icon-history"
					@click="stackUndoDelete(deletedStack)" />

					<!-- <span class="live-relative-timestamp" data-timestamp="{{ deletedStack.deletedAt*1000  }}">{{deletedStack.deletedAt | relativeDateFilter }}</span>
				<a @click="stackUndoDelete(deletedStack)"><span class="icon icon-history"></span></a> -->
			</li>
		</ul>

		<h3>{{ t('deck', 'Deleted cards') }}</h3>
		<ul>
			<li v-for="deletedCard in deletedCards" :key="deletedCard.id">
				<div class="icon icon-deck" />
				<span class="title">{{ deletedCard.title }}</span>
				<button
					:title="t('settings', 'Undo')"
					class="app-navigation-entry-deleted-button icon-history"
					@click="cardUndoDelete(deletedCards)" />
			</li>

			<!-- <li ng-repeat="deletedCard in cardservice.deleted">
				<span class="icon icon-deck"></span>
				<span class="title">{{deletedCard.title}} ({{stackservice.tryAllThenDeleted(deletedCard.stackId).title}})</span>
				<span class="live-relative-timestamp" data-timestamp="{{ deletedCard.deletedAt*1000  }}">{{deletedCard.deletedAt | relativeDateFilter }}</span>
				<a ng-click="cardOrCardAndStackUndoDelete(deletedCard)">
					<span class="icon icon-history"></span>
				</a>
			</li> -->
		</ul>
	</div>
</template>

<script>
import { mapState } from 'vuex'
export default {
	name: 'DeletedTabSidebar',
	components: {

	},
	props: {
		board: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			isLoading: false,
			copiedDeletedStack: null,
			copiedDeletedCard: null
		}
	},
	computed: {
		...mapState({
			deletedStacks: state => state.stack.deletedStacks,
			deletedCards: state => state.stack.deletedCards
		})

	},
	created() {
		this.getData()
	},
	methods: {
		getData() {
			this.isLoading = true
			this.$store.dispatch('deletedItems', this.board.id).then(response => {
				this.isLoading = false
			})
		},
		stackUndoDelete(deletedStack) {
			this.copiedDeletedStack = Object.assign({}, deletedStack)
			this.copiedDeletedStack.deletedAt = 0
			this.$store.dispatch('stackUndoDelete', this.copiedDeletedStack)
		},
		cardUndoDelete(deletedCard) {
			this.copiedDeletedCard = Object.assign({}, deletedCard)
			this.copiedDeletedCard.deletedAt = 0
			this.$store.dispatch('cardUndoDelete', this.copiedDeletedCard)
		}
	}
}
</script>

<style scoped lang="scss">
	ul {
		display: flex;
		flex-direction: row;

		* {
			flex-basis: 44px;
		}

		.title {
			flex-grow: 2;
		}
		.live-relative-timestamp {
			flex-grow: 1;
		}
	}

	li {
	display: flex;
	flex-direction: row;

	* {
		flex-basis: 44px;
	}

	.title {
		flex-grow: 2;
	}
	.live-relative-timestamp {
		flex-grow: 1;
	}
}
</style>
