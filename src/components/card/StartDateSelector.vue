<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<CardDetailEntry :label="t('deck', 'Assign a start date to this card…')">
		<CalendarStart slot="icon" :size="20" />
		<template v-if="!card.done && !card.archived">
			<NcDateTimePickerNative v-if="startdate"
				id="card-startdate-picker"
				v-model="startdate"
				:placeholder="t('deck', 'Set a start date')"
				:hide-label="true"
				type="datetime-local" />
			<NcActions v-if="canEdit"
				:menu-title="!startdate ? t('deck', 'Add start date') : null"
				type="tertiary">
				<template v-if="!startdate" #icon>
					<Plus :size="20" />
				</template>
				<NcActionButton v-if="!startdate"
					close-after-click
					@click="initDate">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ t('deck', 'Choose a date') }}
				</NcActionButton>
				<NcActionButton v-else
					icon="icon-delete"
					close-after-click
					@click="removeStartDate">
					{{ t('deck', 'Remove start date') }}
				</NcActionButton>
			</NcActions>
		</template>
		<template v-else>
			<div v-if="startdate" class="start-info">
				{{ formatReadableDate(startdate) }}
			</div>
		</template>
	</CardDetailEntry>
</template>

<script>
import { defineComponent } from 'vue'
import {
	NcActionButton,
	NcActions,
	NcDateTimePickerNative,
} from '@nextcloud/vue'
import readableDate from '../../mixins/readableDate.js'
import Plus from 'vue-material-design-icons/Plus.vue'
import CalendarStart from 'vue-material-design-icons/CalendarArrowLeft.vue'
import CardDetailEntry from './CardDetailEntry.vue'

export default defineComponent({
	name: 'StartDateSelector',
	components: {
		CardDetailEntry,
		Plus,
		CalendarStart,
		NcActions,
		NcActionButton,
		NcDateTimePickerNative,
	},
	mixins: [
		readableDate,
	],
	props: {
		card: {
			type: Object,
			default: null,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		startdate: {
			get() {
				return this.card?.startdate ? new Date(this.card.startdate) : null
			},
			set(val) {
				this.$emit('input', val ? new Date(val) : null)
			},
		},
	},
	methods: {
		initDate() {
			if (this.startdate === null) {
				const now = new Date()
				now.setHours(8)
				now.setMinutes(0)
				now.setMilliseconds(0)
				this.startdate = now
			}
		},
		removeStartDate() {
			this.startdate = null
			this.$emit('change', null)
		},
	},
})
</script>
<style scoped lang="scss">
.start-info {
	flex-grow: 1;
}
</style>
