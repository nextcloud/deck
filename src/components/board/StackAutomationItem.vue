<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="automation-item">
		<div class="automation-content">
			<span v-if="showStackName" class="stack-name">{{ stackName }}</span>
			<span class="event-badge">{{ eventLabel }}</span>
			<span class="arrow">→</span>
			<span class="action-description">
				{{ actionDescription }}
				<ul v-if="labelNames.length > 0" class="labels">
					<li v-for="label in labelNames" :key="label.id" :style="labelStyle(label)">
						<span>{{ label.title }}</span>
					</li>
				</ul>
			</span>
		</div>
		<div class="automation-actions">
			<NcActions :force-menu="true">
				<NcActionButton :close-after-click="true" icon="icon-rename" @click="$emit('edit', automation)">
					{{ t('deck', 'Edit') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" @click="$emit('clone', automation)">
					{{ t('deck', 'Clone') }}
				</NcActionButton>
				<NcActionButton :close-after-click="true" icon="icon-delete" @click="$emit('delete', automation.id)">
					{{ t('deck', 'Delete') }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcActions, NcActionButton } from '@nextcloud/vue'
import labelStyle from '../../mixins/labelStyle.js'

export default {
	name: 'StackAutomationItem',

	components: {
		NcActions,
		NcActionButton,
	},

	mixins: [labelStyle],

	props: {
		automation: {
			type: Object,
			required: true,
		},
		stackId: {
			type: Number,
			required: false,
			default: null,
		},
		showStackName: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapGetters(['currentBoardLabels', 'stackById']),

		stackName() {
			if (!this.showStackName || !this.automation.stackId) {
				return ''
			}
			const stack = this.stackById(this.automation.stackId)
			return stack?.title || this.t('deck', 'Unknown stack')
		},

		eventLabel() {
			const eventLabels = {
				create: this.t('deck', 'Card created'),
				enter: this.t('deck', 'Card enters'),
				exit: this.t('deck', 'Card exits'),
				delete: this.t('deck', 'Card deleted'),
			}
			return eventLabels[this.automation.event] || this.automation.event
		},

		labelNames() {
			const actionType = this.automation.actionType
			const config = this.automation.actionConfig || {}

			if ((actionType === 'add_label' || actionType === 'remove_label') && config.labelIds) {
				return this.currentBoardLabels.filter(label => config.labelIds.includes(label.id))
			}
			return []
		},

		actionDescription() {
			const actionType = this.automation.actionType
			const config = this.automation.actionConfig || {}

			if (actionType === 'add_label') {
				const count = config.labelIds?.length || 0
				return count === 1
					? this.t('deck', 'Add tag:')
					: this.t('deck', 'Add tags:')
			}

			if (actionType === 'remove_label') {
				const count = config.labelIds?.length || 0
				return count === 1
					? this.t('deck', 'Remove tag:')
					: this.t('deck', 'Remove tags:')
			}

			const descriptions = {
				set_done: this.t('deck', 'Mark as done'),
				remove_done: this.t('deck', 'Unmark as done'),
				archive: this.t('deck', 'Archive card'),
				webhook: this.t('deck', 'Call webhook: {url}', { url: config.url }),
			}

			return descriptions[actionType] || actionType
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/labels';

.automation-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px;
	margin-bottom: 8px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);

	.automation-content {
		display: flex;
		align-items: center;
		gap: 12px;
		flex: 1;

		.stack-name {
			display: inline-block;
			padding: 4px 12px;
			background: var(--color-background-dark);
			color: var(--color-main-text);
			border-radius: 12px;
			font-size: 12px;
			font-weight: 600;
			margin-right: 4px;
		}

		.event-badge {
			display: inline-block;
			padding: 4px 12px;
			background: var(--color-primary-element);
			color: var(--color-primary-element-text);
			border-radius: 12px;
			font-size: 12px;
			font-weight: 500;
		}

		.arrow {
			color: var(--color-text-maxcontrast);
		}

		.action-description {
			flex: 1;
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
	}

	.automation-actions {
		margin-left: 12px;
	}
}
</style>
