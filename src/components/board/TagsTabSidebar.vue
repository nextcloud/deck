<template>
	<div>
		<ul class="labels">
			<li v-for="label in labelsSorted" :key="label.id" :class="{editing: (editingLabelId === label.id)}">
				<!-- Edit Tag -->
				<template v-if="editingLabelId === label.id">
					<form class="label-form" @submit.prevent="updateLabel(label)">
						<NcColorPicker class="color-picker-wrapper" :value="'#' + editingLabel.color" @input="updateColor">
							<div :style="{ backgroundColor: '#' + editingLabel.color }" class="color0 icon-colorpicker" />
						</NcColorPicker>
						<input v-model="editingLabel.title" type="text">
						<input v-tooltip="{content: missingDataLabel, show: !editLabelObjValidated, trigger: 'manual' }"
							:disabled="!editLabelObjValidated"
							type="submit"
							value=""
							class="icon-confirm">
						<NcActions>
							<NcActionButton v-tooltip="{content: missingDataLabel, show: !editLabelObjValidated, trigger: 'manual' }"
								:disabled="!editLabelObjValidated"
								icon="icon-close"
								@click="editingLabelId = null">
								{{ t('deck', 'Cancel') }}
							</NcActionButton>
						</NcActions>
					</form>
				</template>
				<template v-else>
					<div class="label-title" @click="clickEdit(label)">
						<span :style="{ backgroundColor: `#${label.color}`, color: textColor(label.color) }">{{ label.title }}</span>
					</div>
					<NcActions v-if="canManage && !isArchived">
						<NcActionButton icon="icon-rename" @click="clickEdit(label)">
							{{ t('deck', 'Edit') }}
						</NcActionButton>
					</NcActions>
					<NcActions v-if="canManage && !isArchived">
						<NcActionButton icon="icon-delete" @click="deleteLabel(label.id)">
							{{ t('deck', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</template>
			</li>

			<li v-if="addLabel" class="editing">
				<!-- New Tag -->
				<form class="label-form" @submit.prevent="clickAddLabel">
					<NcColorPicker class="color-picker-wrapper" :value="'#' + addLabelObj.color" @input="updateColor">
						<div :style="{ backgroundColor: '#' + addLabelObj.color }" class="color0 icon-colorpicker" />
					</NcColorPicker>
					<input v-model="addLabelObj.title" type="text">
					<input v-tooltip="{content: missingDataLabel, show: !addLabelObjValidated, trigger: 'manual' }"
						:disabled="!addLabelObjValidated"
						type="submit"
						value=""
						class="icon-confirm">
					<NcActions>
						<NcActionButton icon="icon-close" @click="addLabel=false">
							{{ t('deck', 'Cancel') }}
						</NcActionButton>
					</NcActions>
				</form>
			</li>
			<button v-if="canManage && !isArchived" @click="clickShowAddLabel()">
				<span class="icon-add" />{{ t('deck', 'Add a new tag') }}
			</button>
		</ul>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import Color from '../../mixins/color'
import { NcColorPicker, NcActions, NcActionButton } from '@nextcloud/vue'

export default {
	name: 'TagsTabSidebar',
	components: {
		NcColorPicker,
		NcActions,
		NcActionButton,
	},
	mixins: [Color],
	data() {
		return {
			editingLabelId: null,
			editingLabel: null,
			addLabelObj: null,
			addLabel: false,
			missingDataLabel: t('deck', 'title and color value must be provided'),
			defaultColors: ['31CC7C', '17CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'],
		}
	},
	computed: {
		...mapGetters({
			labels: 'currentBoardLabels',
			canManage: 'canManage',
			isArchived: 'isArchived',
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
		},
		labelsSorted() {
			return [...this.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},

	},
	methods: {
		updateColor(c) {
			if (this.editingLabel === null) {
				this.addLabelObj.color = c.substring(1, 7)
			} else {
				this.editingLabel.color = c.substring(1, 7)
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
			this.addLabelObj = { cardId: null, color: this.defaultColors[Math.floor(Math.random() * this.defaultColors.length)], title: '' }
			this.addLabel = true
		},
		clickAddLabel() {
			this.$store.dispatch('addLabelToCurrentBoard', this.addLabelObj)
			this.addLabel = false
			this.addLabelObj = null
		},
	},
}
</script>
<style scoped lang="scss">
	$clickable-area: 44px;

	.labels li {
		display: flex;
		margin-bottom: 3px;
		align-items: stretch;
		height: $clickable-area;

		&:hover {
			background-color: var(--color-background-hover);
			border-radius: 3px;
		}

		.label-title {
			flex-grow: 1;
			padding: 10px;

			&:hover, span:hover {
				cursor: pointer;
			}

			span {
				vertical-align: middle;
				border-radius: 15px;
				padding: 7px 12px;
			}
		}
		&:not(.editing) button {
			width: $clickable-area;
			margin: 0 0 0 -3px;
		}

		.color-picker-wrapper {
			&, &::v-deep > .trigger {
				width: $clickable-area;
				padding: 3px;
				display: flex;
				align-items: stretch;
				position: relative;
			}

			.color0 {
				position: absolute;
				width: calc(#{$clickable-area} - 6px);
				height: calc(#{$clickable-area} - 6px);
				background-size: 14px;
				border-radius: 50%;
			}
		}

		&.editing {
			display: block;
		}
		form {
			display: flex;
			input[type=text] {
				flex-grow: 1;
				margin: 5px;
			}
			input[type=submit] {
				margin-top: 5px;
			}
		}
	}
</style>
