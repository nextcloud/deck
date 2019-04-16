<template>
	<div>
		<ul class="labels">
			<li v-for="label in labels" :key="label.id">
				<template v-if="editingLabelId === label.id">
					<input v-model="editingLabel.title"><input v-model="editingLabel.color">
					<button class="icon-checkmark" @click="updateLabel(label)" :disabled="!editLabelObjValidated" 
					v-tooltip="{content: 'title and color must be provided', 
					show: !editLabelObjValidated, trigger: 'manual' }" />

					<button v-tooltip="t('deck', 'Cancel')" class="icon-close" @click="editingLabelId = null" />
				</template>
				<template v-else>
					<span :style="{ backgroundColor: `#${label.color}`, color: `#white` }" class="label-title">
						<span v-if="label.title">{{ label.title }}</span><i v-if="!label.title"><br></i>
					</span>
					 <button v-tooltip="t('deck', 'Edit')" class="icon-rename" @click="clickEdit(label)" />
					
				
					<button v-tooltip="t('deck', 'Delete')" class="icon-delete" @click="deleteLabel(label.id)" />

				</template>
			</li>
			
			<li v-if="addLabel">
				<template>
				<input v-model="addLabelObj.title"><input v-model="addLabelObj.color">
				<button class="icon-checkmark" @click="clickAddLabel()" :disabled="!addLabelObjValidated" 
					v-tooltip="{content: 'title and color must be provided', 
					show: !addLabelObjValidated, trigger: 'manual' }" />
				<button v-tooltip="t('deck', 'Cancel')" class="icon-close" @click="addLabel=false" />
				</template>
			</li>
			<button v-tooltip="t('deck', 'Add')" class="icon-add" @click="clickShowAddLabel()" />
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
			editingLabel: null,
			addLabelObj: null,
			addLabel: false,
		}
	},
	computed: {
		...mapGetters({
			labels: 'currentBoardLabels'
		}), 
		addLabelObjValidated() {
			if (this.addLabelObj.title == '') return false;
			if (this.addLabelObj.color == '') return false;
			return true;
		},
		editLabelObjValidated() {
			if (this.editingLabel.title == '') return false;
			if (this.editingLabel.color == '') return false;
			return true;
		}
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
		},
		clickShowAddLabel() {
			this.addLabelObj = { boardId: 1, cardId: null, color: '000', title: 'new'}
			this.addLabel=true
		},
		clickAddLabel() {
			this.$store.dispatch('addLabelToCurrentBoard', this.addLabelObj)
			this.addLabel = false
			this.addLabelObj = null
		},
	}
}
</script>
