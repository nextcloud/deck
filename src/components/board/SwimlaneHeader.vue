<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="swimlane-header"
		:class="{'swimlane-header--collapsed': collapsed}"
		:style="headerBorderStyle">
		<button
			class="swimlane-header__toggle"
			:aria-expanded="String(!collapsed)"
			:aria-label="toggleLabel"
			@click="$emit('toggle')">
			<ChevronRightIcon v-if="collapsed" :size="20" decorative />
			<ChevronDownIcon v-else :size="20" decorative />
		</button>

		<span
			v-if="lane.type === 'label' && !isCatchAll"
			class="swimlane-header__label"
			:style="labelStyle(lane)">
			{{ lane.title }}
		</span>
		<NcAvatar
			v-else-if="lane.type === 'assignee' && !isCatchAll"
			:user="lane.uid"
			:displayName="lane.title"
			:isNoUser="lane.participantType !== 0 && lane.participantType !== 6"
			:size="24"
			:disableMenu="true"
			:hideStatus="true" />
		<span
			v-if="lane.type === 'assignee' || isCatchAll"
			class="swimlane-header__title">
			{{ lane.title }}
		</span>

		<!-- raw: plain number like the per-stack count, not "1K". -->
		<NcCounterBubble class="swimlane-header__count" :count="cardCount" raw />

		<!-- The catch-all lane is always last, so it gets no drag handle. -->
		<span
			v-if="canEdit && !isArchived && !collapsed && !isCatchAll"
			class="swimlane-header__drag-handle"
			role="button"
			:aria-label="t('deck', 'Drag to reorder {lane}', { lane: lane.title }, undefined, { escape: false })"
			:title="t('deck', 'Drag to reorder')">
			<DragHorizontalVariantIcon :size="20" decorative />
		</span>
	</div>
</template>

<script>
import { NcAvatar, NcCounterBubble } from '@nextcloud/vue'
import { mapState } from 'pinia'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import DragHorizontalVariantIcon from 'vue-material-design-icons/DragHorizontalVariant.vue'
import { SWIMLANE_NONE_KEY } from '../../constants.js'
import labelStyle from '../../mixins/labelStyle.js'
import { useBoardStore } from '../../stores/board.js'

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

	emits: ['toggle'],
	computed: {
		...mapState(useBoardStore, ['canEdit', 'isArchived']),
		isCatchAll() {
			return this.lane.key === SWIMLANE_NONE_KEY
		},

		toggleLabel() {
			return this.collapsed
				? t('deck', 'Expand {lane}', { lane: this.lane.title }, undefined, { escape: false })
				: t('deck', 'Collapse {lane}', { lane: this.lane.title }, undefined, { escape: false })
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
