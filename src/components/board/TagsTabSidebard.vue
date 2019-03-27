<template>
	<div>
		<ul class="labels">
			<li v-for="label in labels" :key="label.id">
				<template v-if="editingLabelId === label.id">
					<input v-model="editingLabel.title" ><input v-model="editingLabel.color">
					<button @click="updateLabel(label)">save</button><button @click="editingLabelId = null">cancel</button>
				</template>
				<template v-else>
					<span :style="{ backgroundColor: `#${label.color}`, color: `#white` }" class="label-title">
						<span v-if="label.title">{{ label.title }}</span><i v-if="!label.title"><br></i>
					</span>
					<button @click="clickEdit(label)">edit</button><button @click="deleteLabel(label.id)">delete</button>
				</template>
			</li>
		</ul>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'

export default {
	name: 'TagsTabSidebard',
	data() {
		return {
			editingLabelId: null,
			editingLabel: null
		}
	},
	computed: {
		...mapGetters({
			labels: 'currentBoardLabels'
		})
	},
	methods: {
		clickEdit(label) {
			this.editingLabelId = label.id
			this.editingLabel = Object.assign({}, label)
		},
		deleteLabel(id) {
			this.$store.dispatch('removeLabelFromCurrentBoard', id)
		},
		updateLabel(label) {
			this.$store.dispatch('updateLabelFromCurrentBoard', this.editingLabel)
			this.editingLabelId = null
		}
	}
}
</script>
