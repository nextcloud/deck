<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="title"
		size="normal"
		:can-close="!importing"
		@update:open="close">
		<div class="csv-import-modal">
			<div v-if="importing" class="csv-import-modal__progress">
				<NcLoadingIcon :size="44" />
				<p class="csv-import-modal__status">
					{{ t('deck', 'Importing cards...') }}
				</p>
			</div>

			<div v-else class="csv-import-modal__result">
				<div v-for="(msg, index) in messages" :key="'msg-' + index" class="csv-import-modal__line">
					<CheckIcon :size="20" class="csv-import-modal__icon--success" />
					<span>{{ msg }}</span>
				</div>
				<div v-if="messages.length === 0 && errors.length === 0" class="csv-import-modal__line">
					<span>{{ t('deck', 'Nothing to import.') }}</span>
				</div>
				<div v-if="errors.length > 0" class="csv-import-modal__line">
					<AlertCircleOutlineIcon :size="20" class="csv-import-modal__icon--warning" />
					<span>{{ t('deck', 'Import finished with {errors} errors.', { errors: errors.length }) }}</span>
				</div>

				<div v-if="errors.length > 0" class="csv-import-modal__errors">
					<ul>
						<li v-for="(error, index) in errors" :key="'err-' + index">
							{{ error }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<template #actions>
			<NcButton v-if="!importing" type="primary" @click="close">
				{{ t('deck', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcLoadingIcon } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import AlertCircleOutlineIcon from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
	name: 'CsvImportModal',
	components: {
		NcButton,
		NcDialog,
		NcLoadingIcon,
		CheckIcon,
		AlertCircleOutlineIcon,
	},
	props: {
		importing: {
			type: Boolean,
			default: true,
		},
		messages: {
			type: Array,
			default: () => [],
		},
		errors: {
			type: Array,
			default: () => [],
		},
		title: {
			type: String,
			default() {
				return t('deck', 'Import CSV')
			},
		},
	},
	methods: {
		close() {
			if (!this.importing) {
				this.$emit('close')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.csv-import-modal {
	min-height: 100px;

	&__progress {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 12px;
		padding: 20px 0;
	}

	&__status {
		color: var(--color-text-maxcontrast);
	}

	&__result {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	&__line {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__icon--success {
		color: var(--color-success);
	}

	&__icon--warning {
		color: var(--color-warning);
	}

	&__errors {
		margin-top: 4px;
		max-height: 300px;
		overflow-y: auto;

		ul {
			list-style: disc;
			padding-left: 20px;

			li {
				margin-bottom: 4px;
				color: var(--color-text-maxcontrast);
			}
		}
	}
}
</style>
