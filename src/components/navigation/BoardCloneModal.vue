<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="t('deck', 'Clone {boardTitle}', {boardTitle: boardTitle})" :show="true" @close="close(false)">
		<div class="modal__content">
			<NcCheckboxRadioSwitch v-model="withCards">
				{{ t('deck', 'Clone cards') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="withCards" v-model="withAssignments">
				{{ t('deck', 'Clone assignments') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="withCards" v-model="withLabels">
				{{ t('deck', 'Clone labels') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="withCards" v-model="withDueDate">
				{{ t('deck', 'Clone due dates') }}
			</NcCheckboxRadioSwitch>
			<div v-if="withCards" class="accordion" :class="{ 'is-open': accordionOpen }">
				<div class="accordion__toggle" @click="accordionOpen = !accordionOpen">
					<span class="accordion__toggle__icon">
						‣
					</span>
					{{ t('deck', 'Advanced options') }}
				</div>
				<div v-if="accordionOpen" class="accordion__content">
					<NcCheckboxRadioSwitch v-if="withCards" v-model="moveCardsToLeftStack">
						{{ t('deck', 'Move all cards to the first list') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch v-if="withCards" v-model="restoreArchivedCards">
						{{ t('deck', 'Restore archived cards') }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>
		</div>

		<template #actions>
			<NcButton @click="cancel">
				{{ t('deck', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" @click="save">
				{{ t('deck', 'Clone') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcDialog } from '@nextcloud/vue'

export default {
	name: 'BoardCloneModal',
	components: {
		NcDialog,
		NcCheckboxRadioSwitch,
		NcButton,
	},
	props: {
		boardTitle: {
			type: String,
			default: 'Board',
		},
	},
	data() {
		return {
			withCards: false,
			withAssignments: true,
			withLabels: true,
			withDueDate: true,
			moveCardsToLeftStack: false,
			restoreArchivedCards: false,
			accordionOpen: false,
		}
	},
	methods: {
		close(data) {
			this.$emit('close', data)
		},
		save() {
			const data = {
				withCards: this.withCards,
				withAssignments: this.withAssignments,
				withLabels: this.withLabels,
				withDueDate: this.withDueDate,
				moveCardsToLeftStack: this.moveCardsToLeftStack,
				restoreArchivedCards: this.restoreArchivedCards,
			}
			this.close(data)
		},
		cancel() {
			this.close(false)
		},
	},
}
</script>

<style scoped>
.modal__content {
	margin: 20px;
}

.modal__title {
	text-align: center;
}

.modal__buttons {
	text-align: end;
	margin-top: .5em;
}

.accordion__toggle {
	margin: .5em 0;
	cursor: pointer;
}

.accordion__toggle__icon {
	display: inline-block;
}

.accordion.is-open .accordion__toggle__icon {
	transform: rotate(90deg);
}
</style>
