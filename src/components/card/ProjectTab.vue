<template>
	<div v-if="activeTabs.includes('project')" class="section-details">
		<div class="section-wrapper project-tab">
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
<style scoped>
.section-details{
	margin-top: 10px;
	min-width: 500px;
}
</style>
<style lang="scss">
#collection-select-container p {
	display: none;
}

#collection-list li {
	align-items: flex-end;
}

.project-tab .collection-list-item {
	display: flex !important;
	align-items: center !important;
}

.project-tab .linked-icons {
	img {
		height: 20px !important;
	}
}

</style>
