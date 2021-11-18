<!--
  - @copyright Copyright (c) 2021 Max Bachhuber <max.bachhuber@bahuma.io>
  -
  - @author Max Bachhuber <max.bachhuber@bahuma.io>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<Modal size="small" @close="close(false)">
		<div class="modal__content">
			<h2 class="modal__title">
				{{ t('deck', 'Clone {boardTitle}', {boardTitle: boardTitle}) }}
			</h2>
			<CheckboxRadioSwitch :checked.sync="withCards">
				{{ t('deck', 'Clone cards') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch v-if="withCards" :checked.sync="withAssignments">
				{{ t('deck', 'Clone assignments') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch v-if="withCards" :checked.sync="withLabels">
				{{ t('deck', 'Clone labels') }}
			</CheckboxRadioSwitch>
			<CheckboxRadioSwitch v-if="withCards" :checked.sync="withDueDate">
				{{ t('deck', 'Clone due dates') }}
			</CheckboxRadioSwitch>
			<div v-if="withCards" class="accordion" :class="{ 'is-open': accordionOpen }">
				<div class="accordion__toggle" @click="accordionOpen = !accordionOpen">
					<span class="accordion__toggle__icon">
						‣
					</span>
					{{ t('deck', 'Advanced options') }}
				</div>
				<div v-if="accordionOpen" class="accordion__content">
					<CheckboxRadioSwitch v-if="withCards" :checked.sync="moveCardsToLeftStack">
						{{ t('deck', 'Move all cards to the first list') }}
					</CheckboxRadioSwitch>
					<CheckboxRadioSwitch v-if="withCards" :checked.sync="restoreArchivedCards">
						{{ t('deck', 'Restore archived cards') }}
					</CheckboxRadioSwitch>
				</div>
			</div>
			<div class="modal__buttons">
				<button @click="cancel">
					{{ t('deck', 'Cancel') }}
				</button>
				<button class="primary" @click="save">
					{{ t('deck', 'Clone') }}
				</button>
			</div>
		</div>
	</Modal>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'BoardCloneModal',
	components: {
		Modal,
		CheckboxRadioSwitch,
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
