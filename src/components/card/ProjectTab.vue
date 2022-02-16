<template>
	<div v-if="activeTabs.includes('project')" class="section-details">
		<div class="section-wrapper">
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
		activeTabs: {
			type: Array,
			default: () => [],
		},
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
