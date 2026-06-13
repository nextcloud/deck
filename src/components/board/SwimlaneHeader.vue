<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="swimlane-header"
		:class="{'swimlane-header--collapsed': collapsed}"
		:style="headerBorderStyle">
		<button class="swimlane-header__toggle"
			:aria-expanded="String(!collapsed)"
			:aria-label="toggleLabel"
			@click="$emit('toggle')">
			<ChevronRightIcon v-if="collapsed" :size="20" decorative />
			<ChevronDownIcon v-else :size="20" decorative />
		</button>

		<span v-if="lane.type === 'label' && lane.key !== '__none__'"
			class="swimlane-header__label"
			:style="labelStyle(lane)">
			{{ lane.title }}
		</span>
		<NcAvatar v-else-if="lane.type === 'assignee' && lane.key !== '__none__'"
			:user="lane.uid"
			:size="24"
			:disable-menu="true"
			:hide-status="true" />
		<span v-if="lane.type === 'assignee' || lane.key === '__none__'"
			class="swimlane-header__title">
			{{ lane.title }}
		</span>

		<NcCounterBubble class="swimlane-header__count">
			{{ cardCount }}
		</NcCounterBubble>

		<span v-if="canEdit && !collapsed"
			class="swimlane-header__drag-handle"
			role="button"
			:aria-label="t('deck', 'Drag to reorder {lane}', { lane: lane.title })"
			:title="t('deck', 'Drag to reorder')">
			<DragHorizontalVariantIcon :size="20" decorative />
		</span>
	</div>
</template>

<script>
import { NcAvatar, NcCounterBubble } from '@nextcloud/vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import DragHorizontalVariantIcon from 'vue-material-design-icons/DragHorizontalVariant.vue'
import labelStyle from '../../mixins/labelStyle.js'
import { mapGetters } from 'vuex'

export default {
	name: 'SwimlaneHeader',
	components: {
		NcAvatar,
		NcCounterBubble,
		ChevronRightIcon,
		ChevronDownIcon,
		DragHorizontalVariantIcon,
	},
	mixins: [labelStyle],
	props: {
		lane: {
			type: Object,
			required: true,
		},
		cardCount: {
			type: Number,
			default: 0,
		},
		collapsed: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapGetters(['canEdit']),
		toggleLabel() {
			return this.collapsed
				? t('deck', 'Expand {lane}', { lane: this.lane.title })
				: t('deck', 'Collapse {lane}', { lane: this.lane.title })
		},
		headerBorderStyle() {
			if (this.lane.type === 'label' && this.lane.color) {
				return { borderBottomColor: '#' + this.lane.color }
			}
			return {}
		},
	},
}
</script>

<style lang="scss" scoped>
.swimlane-header {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2);
	position: sticky;
	top: 0;
	z-index: 100;
	background-color: var(--color-main-background);
	border-bottom: 2px solid var(--color-border);
	min-height: var(--default-clickable-area);

	&--collapsed {
		border-bottom-color: var(--color-border-dark);
	}

	&__toggle {
		display: flex;
		align-items: center;
		justify-content: center;
		background: none;
		border: none;
		padding: 0;
		cursor: pointer;
		border-radius: var(--border-radius-element);
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		color: var(--color-main-text);

		&:hover {
			background-color: var(--color-background-hover);
		}

		&:focus-visible {
			outline: 2px solid var(--color-primary-element);
			border-radius: var(--border-radius-element);
		}
	}

	&__label {
		display: inline-block;
		padding: 2px 8px;
		border-radius: var(--border-radius-large);
		font-size: 0.85em;
		font-weight: bold;
		white-space: nowrap;
	}

	&__title {
		font-weight: bold;
		white-space: nowrap;
	}

	&__count {
		flex-shrink: 0;
	}

	&__drag-handle {
		margin-inline-start: auto;
		cursor: grab;
		color: var(--color-text-maxcontrast);
		display: flex;
		align-items: center;

		&:hover {
			color: var(--color-main-text);
		}
	}
}
</style>
