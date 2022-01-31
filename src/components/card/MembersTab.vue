<template>
	<div class="section-details">
		<div v-if="showSelelectMembers" @mouseleave="showSelelectMembers = false">
			<Multiselect v-if="canEdit"
				v-model="assignedUsers"
				:multiple="true"
				:options="formatedAssignables"
				:user-select="true"
				:auto-limit="false"
				:placeholder="t('deck', 'Assign a user to this card…')"
				label="displayname"
				track-by="multiselectKey"
				@select="assignUserToCard"
				@remove="removeUserFromCard">
				<template #tag="scope">
					<div class="avatarlist--inline">
						<Avatar :user="scope.option.uid"
							:display-name="scope.option.displayname"
							:size="24"
							:is-no-user="scope.option.isNoUser"
							:disable-menu="true" />
					</div>
				</template>
			</Multiselect>
			<div v-else class="avatar-list--readonly">
				<Avatar v-for="option in assignedUsers"
					:key="option.primaryKey"
					:user="option.uid"
					:display-name="option.displayname"
					:is-no-user="option.isNoUser"
					:size="32" />
			</div>
		</div>
		<template v-else>
			<div class="members">
				<Avatar v-for="option in assignedUsers"
					:key="option.primaryKey"
					:user="option.uid"
					:display-name="option.displayname"
					:is-no-user="option.isNoUser"
					:size="32" />
				<div class="button new select-member-btn" @click="selectMembers">
					<span class="icon icon-add" />
					<span class="hidden-visually" />
				</div>
			</div>
		</template>
	</div>
</template>

<script>
import { Multiselect, Avatar } from '@nextcloud/vue'
import { mapState, mapGetters } from 'vuex'

export default {
	name: 'MembersTab',
	components: {
		Multiselect,
		Avatar,
	},
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			assignedUsers: null,
			copiedCard: null,
			showSelelectMembers: false,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters(['canEdit', 'assignables']),
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
		selectMembers() {
			this.showSelelectMembers = true
			this.$emit('active-tab', 'members')
		},
		removeUserFromCard(user) {
			this.$store.dispatch('removeUserFromCard', {
				card: this.copiedCard,
				assignee: {
					userId: user.uid,
					type: user.type,
				},
			})
		},
		addLabelToCard(newLabel) {
			this.copiedCard.labels.push(newLabel)
			const data = {
				card: this.copiedCard,
				labelId: newLabel.id,
			}
			this.$store.dispatch('addLabel', data)
		},
		assignUserToCard(user) {
			this.$store.dispatch('assignCardToUser', {
				card: this.copiedCard,
				assignee: {
					userId: user.uid,
					type: user.type,
				},
			})
		},
		async initialize() {
			if (!this.card) {
				return
			}

			this.copiedCard = JSON.parse(JSON.stringify(this.card))
			this.assignedLabels = [...this.card.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)

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
	},
}
</script>

<style scoped>
.select-member-btn {
	box-sizing: border-box;
	display: flex;
	height: 32px;
	width: 32px;
	padding: 9px;
	align-items: center;
	justify-content: center;
}

.section-details {
	width: 100%;
}

.members {
	display: flex;
	align-items: center;
}
</style>
