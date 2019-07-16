<template>
	<div>
		<h3>{{ t('deck', 'Deleted stacks') }}</h3>

		<ul>
			<!-- <li ng-repeat="deletedStack in stackservice.deleted">
				<span class="icon icon-deck"></span>
				<span class="title">{{deletedStack.title}}</span>
				<span class="live-relative-timestamp" data-timestamp="{{ deletedStack.deletedAt*1000  }}">{{deletedStack.deletedAt | relativeDateFilter }}</span>
				<a ng-click="stackUndoDelete(deletedStack)"><span class="icon icon-history"></span></a>
			</li> -->
		</ul>

		<h3>{{ t('deck', 'Deleted cards') }}</h3>
		<ul>
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
	name: 'DeletedTabSidebard',
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
			isLoading: false
		}
	},
	computed: {
		...mapState({
			deletedStacks: state => state.deletedStacks
			// deletedCards: state => state.deletedCards
		})

	},
	created() {
		this.getData()
	},
	methods: {
		getData() {
			this.isLoading = true

			this.$store.dispatch('deletedItems', 1).then(response => {
				this.isLoading = false
			})
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
</style>
