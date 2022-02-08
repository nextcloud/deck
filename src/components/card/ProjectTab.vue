<template>
	<div class="section-details">
		<div class="section-wrapper" @click="$emit('active-tab', 'project')">
			<CollectionList v-if="card.id"
				:id="`${card.id}`"
				:name="card.title"
				type="deck-card" />
		</div>
	</div>
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'

export default {
	components: { CollectionList },
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
		}
	},
	computed: {
	},
	mounted() {
	},
	methods: {
	},
}
</script>

<style lang="scss">
#collection-select-container p {
	display: none;
}

#collection-list li {
	align-items: flex-end;
}
</style>
