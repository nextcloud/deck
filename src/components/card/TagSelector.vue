<template>
	<div class="selector-wrapper" :aria-label="t('deck', 'Assign a tag to this card…')" data-test="tag-selector">
		<div class="selector-wrapper--icon">
			<TagMultiple :size="20" />
		</div>
		<NcSelect :value="assignedLabels"
			class="selector-wrapper--selector"
			:multiple="true"
			:disabled="disabled"
			:options="labelsSorted"
			:aria-label-combobox="t('deck', 'Assign a tag to this card…')"
			:placeholder="t('deck', 'Select or create a tag…')"
			:taggable="true"
			:close-on-select="false"
			label="title"
			track-by="id"
			tag-position="bottom"
			@option:selected="onSelect"
			@option:deselected="onRemove"
			@option:created="onNewTag">
			<template #option="scope">
				<div v-if="!scope?.isTag" :style="{ backgroundColor: '#' + scope.color, color: textColor(scope.color)}" class="tag">
					{{ scope.title }}
				</div>
				<div v-else>
					{{ t('deck', 'Create a new tag:') }} <div class="tag">
						{{ scope.label }}
					</div>
				</div>
			</template>
			<template #selected-option="scope">
				<div :style="{ backgroundColor: '#' + scope.color, color: textColor(scope.color)}" class="tag">
					{{ scope.title }}
				</div>
			</template>
		</NcSelect>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import Color from '../../mixins/color.js'
import TagMultiple from 'vue-material-design-icons/TagMultiple.vue'

export default {
	name: 'TagSelector',
	components: { TagMultiple, NcSelect },
	mixins: [Color],
	props: {
		card: {
			type: Object,
			default: null,
		},
		labels: {
			type: Array,
			default: () => [],
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		labelsSorted() {
			return [...this.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
				.filter(label => this.card.labels.findIndex((l) => l.id === label.id) === -1)
		},
		assignedLabels() {
			return [...this.card.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
	},
	methods: {
		onSelect(options) {
			const addedLabel = options.filter(option => !this.card.labels.includes(option))
			this.$emit('select', addedLabel[0])
		},
		onRemove(removedLabel) {
			this.$emit('remove', removedLabel)
		},
		async onNewTag(option) {
			this.$emit('newtag', option.title)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/selector';

.v-select:deep(.vs__selected) {
	padding-left: 0 !important;
}

.tag {
	flex-grow: 0;
	flex-shrink: 1;
	overflow: hidden;
	padding: 3px 12px;
	display: inline-block;
	border-radius: var(--border-radius-pill);
	margin-right: 3px;
}
</style>
