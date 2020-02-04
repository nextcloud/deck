<template>
	<ActivityList v-if="$parent.isActive"
		filter="deck"
		:object-id="board.id"
		object-type="deck"
		type="deck" />
</template>

<script>
import ActivityList from '../ActivityList'

export default {
	name: 'TimelineTabSidebar',
	components: {
		ActivityList,
	},
	props: {
		board: {
			type: Object,
			default: undefined,
		},
	},
}
</script>
