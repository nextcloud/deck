<template>
	<div>
		<ul class="labels">
			<li v-for="label in labels" :key="label.id" :class="{editing: (editingLabelId === label.id)}">
				<!-- Edit Tag -->
				<template v-if="editingLabelId === label.id">
					<form class="label-form" @submit.prevent="updateLabel(label)">
						<ColorPicker class="color-picker-wrapper" :value="'#' + editingLabel.color" @input="updateColor">
							<div :style="{ backgroundColor: '#' + editingLabel.color }" class="color0 icon-colorpicker" />
						</ColorPicker>
						<input v-model="editingLabel.title" type="text">
						<input v-tooltip="{content: missingDataLabel, show: !editLabelObjValidated, trigger: 'manual' }"
							:disabled="!editLabelObjValidated"
							type="submit"
							value=""
							class="icon-confirm">
						<input v-tooltip="t('deck', 'Cancel')"
							value=""
							class="icon-close"
							@click="editingLabelId = null">
					</form>
				</template>
				<template v-else>
					<div :style="{ backgroundColor: `#${label.color}`, color:textColor(label.color) }" class="label-title">
						<span>{{ label.title }}</span>
					</div>
					<button v-if="canManage"
						v-tooltip="t('deck', 'Edit')"
						class="icon-rename"
						@click="clickEdit(label)" />
					<button v-if="canManage"
						v-tooltip="t('deck', 'Delete')"
						class="icon-delete"
						@click="deleteLabel(label.id)" />
				</template>
			</li>

			<li v-if="addLabel" class="editing">
				<!-- New Tag -->
				<template>
					<form class="label-form" @submit.prevent="clickAddLabel">
						<ColorPicker class="color-picker-wrapper" :value="'#' + addLabelObj.color" @input="updateColor">
							<div :style="{ backgroundColor: '#' + addLabelObj.color }" class="color0 icon-colorpicker" />
						</ColorPicker>
						<input v-model="addLabelObj.title" type="text">
						<input v-tooltip="{content: missingDataLabel, show: !addLabelObjValidated, trigger: 'manual' }"
							:disabled="!addLabelObjValidated"
							type="submit"
							value=""
							class="icon-confirm">
						<input v-tooltip="t('deck', 'Cancel')"
							value=""
							class="icon-close"
							@click="addLabel=false">
					</form>
				</template>
			</li>
			<button v-if="canManage" @click="clickShowAddLabel()">
				<span class="icon-add" />{{ t('deck', 'Add a new tag') }}
			</button>
		</ul>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import Color from '../../mixins/color'
import { ColorPicker } from '@nextcloud/vue'

export default {
	name: 'TagsTabSidebar',
	components: {
		ColorPicker,
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
	$clickable-area: 37px;

	.labels li {
		display: flex;
		margin-bottom: 3px;
		align-items: stretch;
		height: $clickable-area;

		.label-title {
			flex-grow: 1;
			border-radius: 3px;
			padding: 7px;
			span {
				vertical-align: middle;
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
			}
		}
		button,
		input:not([type='text']):last-child {
			min-width: $clickable-area;
			border-radius: 0 var(--border-radius) var(--border-radius) 0;
			margin-left: -1px;
			width: 35px;
			background-color: var(--color-main-background);
		}
	}
</style>
