<template>
	<div>
		<ul class="labels">
			<li v-for="label in labels" :key="label.id">
				<template v-if="editingLabelId === label.id">
					<input v-model="editingLabel.title">
					<compact-picker :value="editingLabel.color" :palette="defaultColors" @input="updateColor" />
					<button v-tooltip="{content: missingDataLabel, show: !editLabelObjValidated, trigger: 'manual' }" :disabled="!editLabelObjValidated" class="icon-checkmark"
						@click="updateLabel(label)" />

					<button v-tooltip="t('deck', 'Cancel')" class="icon-close" @click="editingLabelId = null" />
				</template>
				<template v-else>
					<span :style="{ backgroundColor: `#${label.color}`, color:textColor(label.color) }" class="label-title">
						<span>{{ label.title }}</span>
					</span>
					<button v-tooltip="t('deck', 'Edit')" class="icon-rename" @click="clickEdit(label)" />
					<button v-tooltip="t('deck', 'Delete')" class="icon-delete" @click="deleteLabel(label.id)" />
				</template>
			</li>

			<li v-if="addLabel">
				<template>
					<input v-model="addLabelObj.title">
					<compact-picker :palette="defaultColors" value="" @input="updateColor" />
					<button v-tooltip="{content: missingDataLabel, show: !addLabelObjValidated, trigger: 'manual' }" :disabled="!addLabelObjValidated" class="icon-checkmark"
						@click="clickAddLabel()" />
					<button v-tooltip="t('deck', 'Cancel')" class="icon-close" @click="addLabel=false" />
				</template>
			</li>
			<button v-tooltip="t('deck', 'Add')" class="icon-add" @click="clickShowAddLabel()" />
		</ul>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import Color from '../../mixins/color'
import { Compact } from 'vue-color'

export default {
	name: 'TagsTabSidebard',
	components: {
		'compact-picker': Compact
	},
	mixins: [Color],
	data() {
		return {
			editingLabelId: null,
			editingLabel: null,
			addLabelObj: null,
			addLabel: false,
			missingDataLabel: t('deck', 'title and color value must be provided'),
			defaultColors: ['#31CC7C', '#317CCC', '#FF7A66', '#F1DB50', '#7C31CC', '#CC317C', '#3A3B3D', '#CACBCD']
		}
	},
	computed: {
		...mapGetters({
			labels: 'currentBoardLabels'
		}),
		addLabelObjValidated() {
			if (this.addLabelObj.title === '') {
				return false
			}

			if (this.colorIsValid(this.addLabelObj.color) === false) {
				return false
			}

			return true
		},
		editLabelObjValidated() {
			if (this.editingLabel.title === '') {
				return false
			}

			if (this.colorIsValid(this.editingLabel.color) === false) {
				return false
			}

			return true
		}

	},
	methods: {
		updateColor(c) {
			if (this.editingLabel === null) {
				this.addLabelObj.color = c.hex.substring(1, 7)
			} else {
				this.editingLabel.color = c.hex.substring(1, 7)
			}
		},
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
			this.addLabelObj = { cardId: null, color: '000', title: '' }
			this.addLabel = true
		},
		clickAddLabel() {
			this.$store.dispatch('addLabelToCurrentBoard', this.addLabelObj)
			this.addLabel = false
			this.addLabelObj = null
		}
	}
}
</script>
