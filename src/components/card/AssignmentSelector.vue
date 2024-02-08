<template>
	<div class="selector-wrapper" :aria-label="t('deck', 'Assign to users/groups/circles')" data-test="assignment-selector">
		<div class="selector-wrapper--icon">
			<AccountMultiple :size="20" />
		</div>
		<NcSelect v-if="canEdit"
			v-model="assignedUsers"
			class="selector-wrapper--selector"
			:disabled="assignables.length === 0"
			:multiple="true"
			:options="formatedAssignables"
			:user-select="true"
			:aria-label-combobox="t('deck', 'Assign a user to this card…')"
			:placeholder="t('deck', 'Select a user to assign to this card…')"
			label="displayname"
			track-by="multiselectKey"
			@option:selected="onSelect"
			@option:deselected="onRemove">
			<template #tag="scope">
				<div class="avatarlist--inline">
					<NcAvatar :user="scope.option.uid"
						:display-name="scope.option.displayname"
						:size="24"
						:is-no-user="scope.option.isNoUser"
						:disable-menu="true" />
				</div>
			</template>
		</NcSelect>
		<div v-else class="avatar-list--readonly">
			<NcAvatar v-for="option in assignedUsers"
				:key="option.primaryKey"
				:user="option.uid"
				:display-name="option.displayname"
				:is-no-user="option.isNoUser"
				:size="32" />
		</div>
	</div>
</template>

<script>
import { defineComponent } from 'vue'
import { NcAvatar, NcSelect } from '@nextcloud/vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'

export default defineComponent({
	name: 'AssignmentSelector',
	components: {
		AccountMultiple,
		NcSelect,
		NcAvatar,
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
	data() {
		return {
			assignedUsers: [],
		}
	},
	computed: {
		formatedAssignables() {
			return this.assignables.map(item => {
				const assignable = {
					...item,
					user: item.primaryKey,
					displayName: item.displayname,
					icon: 'icon-user',
					isNoUser: false,
					multiselectKey: item.type + ':' + item.uid,
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
					isNoUser: item.participant.type !== 0,
					multiselectKey: item.participant.type + ':' + item.participant.primaryKey,
				}))
			} else {
				this.assignedUsers = []
			}
		},
		onSelect(options) {
			const addition = options.filter((item) => !this.card.assignedUsers.find((user) => user.participant.primaryKey === item.primaryKey))
			this.$emit('select', addition[0])
		},
		onRemove(removed) {
			this.$emit('remove', removed)
		},
	},
})
</script>

<style lang="scss" scoped>
@import '../../css/selector';
</style>
