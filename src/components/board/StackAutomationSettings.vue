<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal v-if="show"
		:name="t('deck', 'Stack automations')"
		@close="$emit('close')">
		<div class="stack-automation-settings">
			<p class="automation-description">
				{{ t('deck', 'Configure actions that are automatically executed when a specific event is triggered.') }}
			</p>

			<div v-if="automations.length > 0" class="automations-list">
				<StackAutomationItem v-for="automation in automations"
					:key="automation.id"
					:automation="automation"
					:stack-id="stackId"
					@edit="editAutomation"
					@clone="cloneAutomation"
					@delete="deleteAutomation" />
			</div>

			<div v-else class="empty-content">
				<p>{{ t('deck', 'No automations configured yet.') }}</p>
			</div>

			<NcButton class="add-automation-button"
				type="primary"
				@click="showAddForm = true">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('deck', 'Add automation') }}
			</NcButton>

			<!-- Add/Edit automation form -->
			<div v-if="showAddForm" class="automation-form">
				<h3>{{ editingAutomation ? t('deck', 'Edit automation') : t('deck', 'Add automation') }}</h3>

				<NcSelect v-model="formEvent"
					:options="eventOptions"
					:placeholder="t('deck', 'Select event')"
					label="label"
					track-by="value" />

				<NcSelect v-model="formActionType"
					:options="actionTypeOptions"
					:placeholder="t('deck', 'Select action')"
					label="label"
					track-by="value" />

				<!-- Action-specific configuration -->
				<div v-if="formActionType" class="action-config">
					<template v-if="formActionType.value === 'add_label' || formActionType.value === 'remove_label'">
						<TagSelector
							v-model="selectedLabels"
							:labels="boardLabels" />
					</template>

					<template v-if="formActionType.value === 'webhook'">
						<input v-model="webhookUrl"
							type="url"
							:placeholder="t('deck', 'Webhook URL')"
							class="webhook-input">
						<NcSelect v-model="webhookMethod"
							:options="['GET', 'POST']"
							:placeholder="t('deck', 'HTTP Method')" />
					</template>
				</div>

				<div class="form-actions">
					<NcButton @click="cancelForm">
						{{ t('deck', 'Cancel') }}
					</NcButton>
					<NcButton type="primary"
						:disabled="!isFormValid"
						@click="saveAutomation">
						{{ editingAutomation ? t('deck', 'Update') : t('deck', 'Create') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import { NcButton } from '@nextcloud/vue'
import { NcSelect } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import StackAutomationItem from './StackAutomationItem.vue'
import TagSelector from '../card/TagSelector.vue'
import { mapGetters } from 'vuex'

export default {
	name: 'StackAutomationSettings',

	components: {
		NcModal,
		NcButton,
		NcSelect,
		Plus,
		StackAutomationItem,
		TagSelector,
	},

	props: {
		show: {
			type: Boolean,
			default: false,
		},
		stackId: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			showAddForm: false,
			editingAutomation: null,
			formEvent: null,
			formActionType: null,
			selectedLabels: [],
			webhookUrl: '',
			webhookMethod: 'POST',
		}
	},

	computed: {
		...mapGetters([
			'currentBoardLabels',
		]),

		automations() {
			return this.$store.getters.automationsByStack(this.stackId)
		},

		boardLabels() {
			return this.currentBoardLabels
		},

		eventOptions() {
			return [
				{ value: 'create', label: this.t('deck', 'Card created') },
				{ value: 'enter', label: this.t('deck', 'Card enters stack') },
				{ value: 'exit', label: this.t('deck', 'Card exits stack') },
				{ value: 'delete', label: this.t('deck', 'Card deleted') },
			]
		},

		actionTypeOptions() {
			return [
				{ value: 'add_label', label: this.t('deck', 'Add tag') },
				{ value: 'remove_label', label: this.t('deck', 'Remove tag') },
				{ value: 'set_done', label: this.t('deck', 'Mark as done') },
				{ value: 'remove_done', label: this.t('deck', 'Unmark as done') },
				{ value: 'archive', label: this.t('deck', 'Archive card') },
				{ value: 'webhook', label: this.t('deck', 'Call webhook') },
			]
		},

		isFormValid() {
			if (!this.formEvent || !this.formActionType) {
				return false
			}

			const actionType = this.formActionType.value
			if ((actionType === 'add_label' || actionType === 'remove_label') && this.selectedLabels.length === 0) {
				return false
			}

			if (actionType === 'webhook' && !this.webhookUrl) {
				return false
			}

			return true
		},
	},

	watch: {
		show(newVal) {
			if (newVal) {
				this.loadAutomations()
			}
		},

		formActionType(newValue, oldValue) {
			// Preserve tags when switching between add_label and remove_label
			const labelActions = ['add_label', 'remove_label']
			const bothAreLabelActions = labelActions.includes(newValue?.value) && labelActions.includes(oldValue?.value)
			const isLabelAction = labelActions.includes(newValue?.value)
			
			// Don't reset selectedLabels if:
			// 1. Both old and new are label actions (switching between add/remove)
			// 2. oldValue is null and newValue is a label action (initial edit load)
			if (!bothAreLabelActions && !(oldValue === null && isLabelAction)) {
				// Reset action-specific config when switching to a different action type
				this.selectedLabels = []
			}
			
			// Always reset webhook config when not switching to webhook
			if (newValue?.value !== 'webhook') {
				this.webhookUrl = ''
				this.webhookMethod = 'POST'
			}
		},
	},

	methods: {
		async loadAutomations() {
			try {
				await this.$store.dispatch('loadStackAutomations', this.stackId)
			} catch (error) {
				console.error('Failed to load automations', error)
			}
		},

		editAutomation(automation) {
			this.editingAutomation = automation
			this.showAddForm = true

			// Find the event option
			const eventOption = this.eventOptions.find(opt => opt.value === automation.event)
			this.formEvent = eventOption

			// Find the action type option
			const actionTypeOption = this.actionTypeOptions.find(opt => opt.value === automation.actionType)
			this.formActionType = actionTypeOption

			// Load config based on action type
			const config = automation.actionConfig || {}
			if (automation.actionType === 'add_label' || automation.actionType === 'remove_label') {
				// Map labelIds to full label objects
				if (config.labelIds) {
					this.selectedLabels = this.currentBoardLabels.filter(label =>
						config.labelIds.includes(label.id)
					)
				}
			} else if (automation.actionType === 'webhook') {
				this.webhookUrl = config.url || ''
				this.webhookMethod = config.method || 'POST'
			}
		},

		cloneAutomation(automation) {
			// Reset editing automation so it creates a new one
			this.editingAutomation = null
			this.showAddForm = true

			// Find the event option
			const eventOption = this.eventOptions.find(opt => opt.value === automation.event)
			this.formEvent = eventOption

			// Find the action type option
			const actionTypeOption = this.actionTypeOptions.find(opt => opt.value === automation.actionType)
			this.formActionType = actionTypeOption

			// Load config based on action type
			const config = automation.actionConfig || {}
			if (automation.actionType === 'add_label' || automation.actionType === 'remove_label') {
				// Map labelIds to full label objects
				if (config.labelIds) {
					this.selectedLabels = this.currentBoardLabels.filter(label =>
						config.labelIds.includes(label.id)
					)
				}
			} else if (automation.actionType === 'webhook') {
				this.webhookUrl = config.url || ''
				this.webhookMethod = config.method || 'POST'
			}
		},

		async saveAutomation() {
			const config = this.buildConfig()

			try {
				if (this.editingAutomation) {
					await this.$store.dispatch('updateStackAutomation', {
						stackId: this.stackId,
						id: this.editingAutomation.id,
						event: this.formEvent.value,
						actionType: this.formActionType.value,
						config,
						order: this.editingAutomation.order,
					})
				} else {
					await this.$store.dispatch('createStackAutomation', {
						stackId: this.stackId,
						event: this.formEvent.value,
						actionType: this.formActionType.value,
						config,
						order: this.automations.length,
					})
				}

				this.cancelForm()
			} catch (error) {
				console.error('Failed to save automation', error)
			}
		},

		buildConfig() {
			const actionType = this.formActionType.value

			if (actionType === 'add_label' || actionType === 'remove_label') {
				return { labelIds: this.selectedLabels.map(label => label.id) }
			}

			if (actionType === 'webhook') {
				return {
					url: this.webhookUrl,
					method: this.webhookMethod,
				}
			}

			return {}
		},

		async deleteAutomation(automationId) {
			try {
				await this.$store.dispatch('deleteStackAutomation', {
					stackId: this.stackId,
					id: automationId,
				})
			} catch (error) {
				console.error('Failed to delete automation', error)
			}
		},

		cancelForm() {
			this.showAddForm = false
			this.editingAutomation = null
			this.formEvent = null
			this.formActionType = null
			this.selectedLabels = []
			this.webhookUrl = ''
			this.webhookMethod = 'POST'
		},
	},
}
</script>

<style lang="scss" scoped>
.stack-automation-settings {
	padding: 20px;
	min-height: 400px;

	.automation-description {
		margin-bottom: 20px;
		color: var(--color-text-maxcontrast);
	}

	.automations-list {
		margin-bottom: 20px;
	}

	.empty-content {
		text-align: center;
		padding: 40px 0;
		color: var(--color-text-maxcontrast);
	}

	.add-automation-button {
		margin-bottom: 20px;
	}

	.automation-form {
		border-top: 1px solid var(--color-border);
		padding-top: 20px;
		margin-top: 20px;

		h3 {
			margin-bottom: 16px;
		}

		> * {
			margin-bottom: 12px;
		}

		.action-config {
			margin-top: 12px;

			.webhook-input {
				width: 100%;
				padding: 8px;
				margin-bottom: 8px;
			}
		}

		.form-actions {
			display: flex;
			gap: 8px;
			justify-content: flex-end;
			margin-top: 20px;
		}
	}
}
</style>
