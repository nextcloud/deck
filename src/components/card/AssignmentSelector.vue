<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="selector-wrapper" :aria-label="t('deck', 'Assign to users/groups/team')" data-test="assignment-selector">
		<div class="selector-wrapper--icon">
			<AccountMultiple :size="20" />
		</div>
		<NcSelectUsers
			v-if="canEdit"
			:modelValue="assignedUsers"
			class="selector-wrapper--selector"
			:disabled="assignables.length === 0"
			:multiple="true"
			:options="formatedAssignables"
			:aria-label-combobox="t('deck', 'Assign a user to this card…')"
			:placeholder="t('deck', 'Select a user to assign to this card…')"
			@update:modelValue="onUpdate" />
		<div v-else class="avatar-list--readonly">
			<NcUserBubble
				v-for="option in assignedUsers"
				:key="option.primaryKey"
				:user="option.uid"
				:displayName="option.displayname"
				:isNoUser="option.isNoUser"
				:size="32" />
		</div>
	</div>
</template>

<script>
import { NcSelectUsers, NcUserBubble } from '@nextcloud/vue'
import { defineComponent } from 'vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultipleOutline.vue'

export default defineComponent({
	name: 'AssignmentSelector',
	components: {
		AccountMultiple,
		NcSelectUsers,
		NcUserBubble,
	},

	props: {
		card: {
			type: Object,
			default: null,
		},

		canEdit: {
			type: Boolean,
			default: true,
		},

		assignables: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['select', 'remove'],
	data() {
		return {
			assignedUsers: [],
		}
	},

	computed: {
		formatedAssignables() {
			return this.assignables.map((item) => {
				const assignable = {
					...item,
					id: item.type + ':' + item.uid,
					user: item.primaryKey,
					displayName: item.displayname,
					icon: 'icon-user',
					isNoUser: false,
				}

				if (item.type === 1) {
					assignable.icon = 'icon-group'
					assignable.isNoUser = true
				}
				if (item.type === 7) {
					assignable.icon = 'icon-circles'
					assignable.isNoUser = true
				}

				return assignable
			})
		},
	},

	watch: {
		card() {
			this.initialize()
		},
	},

	mounted() {
		this.initialize()
	},

	methods: {
		async initialize() {
			if (!this.card) {
				return
			}

			if (this.card.assignedUsers && this.card.assignedUsers.length > 0) {
				this.assignedUsers = this.card.assignedUsers.map((item) => ({
					...item.participant,
					id: item.participant.type + ':' + item.participant.uid,
					displayName: item.participant.displayname,
					isNoUser: item.participant.type !== 0,
					user: item.participant.uid,
				}))
			} else {
				this.assignedUsers = []
			}
		},

		onUpdate(value) {
			const added = value.filter((item) => !this.assignedUsers.some((user) => user.id === item.id))
			const removed = this.assignedUsers.filter((user) => !value.some((item) => item.id === user.id))
			this.assignedUsers = value
			added.forEach((item) => this.$emit('select', item))
			removed.forEach((item) => this.$emit('remove', item))
		},
	},
})
</script>

<style lang="scss" scoped>
@use '../../css/selector.scss';
</style>
