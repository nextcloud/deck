<template>
	<div class="selector-wrapper" :aria-label="t('deck', 'Assign to users/groups/circles')" data-test="assignment-selector">
		<div class="selector-wrapper--icon">
			<AccountMultiple :size="20" />
		</div>
		<NcMultiselect v-if="canEdit"
			v-model="assignedUsers"
			class="selector-wrapper--selector"
			:disabled="assignables.length === 0"
			:multiple="true"
			:options="formatedAssignables"
			:user-select="true"
			:auto-limit="false"
			:placeholder="t('deck', 'Assign a user to this card…')"
			label="displayname"
			track-by="multiselectKey"
			@select="onSelect"
			@remove="onRemove">
			<template #tag="scope">
				<div class="avatarlist--inline">
					<NcAvatar :user="scope.option.uid"
						:display-name="scope.option.displayname"
						:size="24"
						:is-no-user="scope.option.isNoUser"
						:disable-menu="true" />
				</div>
			</template>
		</NcMultiselect>
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
import { NcAvatar, NcMultiselect } from '@nextcloud/vue'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'

export default defineComponent({
	name: 'AssignmentSelector',
	components: {
		AccountMultiple,
		NcMultiselect,
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
		onSelect(user) {
			this.$emit('select', user)
		},
		onRemove(user) {
			this.$emit('remove', user)
		},
	},
})
</script>

<style lang="scss" scoped>
@import '../../css/selector';
</style>
