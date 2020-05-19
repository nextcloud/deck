<template>
	<div>
		<ul>
			<li v-for="archivedCard in archivedCards" :key="archivedCard.id">
				<CardItem :id="archivedCard.id" />
			</li>
		</ul>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import relativeDate from '../../mixins/relativeDate'
import CardItem from '../cards/CardItem'

export default {
	name: 'ArchivedTabSidebar',
	components: {
		CardItem,
	},
	mixins: [ relativeDate ],
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
		...mapGetters([
			'archivedCards',
		]),

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

</style>
