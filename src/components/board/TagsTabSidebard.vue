<template>
	<div>
		{{labelByCurrentBoard}}
		<ul class="labels">
			<li v-for="label in labels" :key="label.id">
				<template v-if="editingLabelId === label.id">
				<input :value="label.title"><input :value="label.color">
				<button @click="updateLabel()">save</button><button @click="editingLabelId = null">cancel</button>
				</template>
				<template v-else>
				<span :style="{ backgroundColor: `#${label.color}`, color: `#white` }" class="label-title">
					<span v-if="label.title">{{ label.title }}</span><i v-if="!label.title"><br></i>
				</span>
				<button @click="editingLabelId=label.id">edit</button><button @click="deleteLabel(label.id)">delete</button>
				</template>
				
			</li>
		</ul>
	</div>
</template>

<script>

export default {
	name: 'TagsTabSidebard',
	components: {
		
	},
	
	data() {
		return {
			editingLabelId: null,
		}
	},
	props: {
		labels: {
			type: Object,
			default: undefined
		}
	},
	methods: {
		deleteLabel(id) {
			this.$store.dispatch('removeLabel', id)
		},
		updateLabel(id, name) {
			
		}
	},
	computed: {
		labelByCurrentBoard() {
			return (id) => this.$store.getters.labelByCurrentBoard()
		}
	},
	
		
}
</script>
