<template>
	<div>
		<Multiselect
			v-if="canShare"
			v-model="addAcl"
			:placeholder="t('deck', 'Share board with a user, group or circle …')"
			:options="formatedSharees"
			:user-select="true"
			label="displayName"
			track-by="user"
			:internal-search="false"
			@input="clickAddAcl"
			@search-change="asyncFind" />

		<ul
			id="shareWithList"
			class="shareWithList">
			<li>
				<Avatar :user="board.owner.uid" />
				<span class="has-tooltip username">
					{{ board.owner.displayname }}
					<span v-if="!isCurrentUser(board.owner.uid)" class="board-owner-label">
						{{ t('deck', 'Board owner') }}
					</span>
				</span>
			</li>
			<li v-for="acl in board.acl" :key="acl.participant.primaryKey">
				<Avatar v-if="acl.type===0" :user="acl.participant.uid" />
				<div v-if="acl.type===1" class="avatardiv icon icon-group" />
				<div v-if="acl.type===7" class="avatardiv icon icon-circles" />
				<span class="has-tooltip username">
					{{ acl.participant.displayname }}
					<span v-if="acl.type===1">{{ t('deck', '(Group)') }}</span>
					<span v-if="acl.type===7">{{ t('deck', '(Circle)') }}</span>
				</span>

				<ActionCheckbox v-if="!(isCurrentUser(acl.participant.uid) && acl.type === 0) && (canManage || (canEdit && canShare))" :checked="acl.permissionEdit" @change="clickEditAcl(acl)">
					{{ t('deck', 'Can edit') }}
				</ActionCheckbox>
				<Actions v-if="!(isCurrentUser(acl.participant.uid) && acl.type === 0)" :force-menu="true">
					<ActionCheckbox v-if="canManage || canShare" :checked="acl.permissionShare" @change="clickShareAcl(acl)">
						{{ t('deck', 'Can share') }}
					</ActionCheckbox>
					<ActionCheckbox v-if="canManage" :checked="acl.permissionManage" @change="clickManageAcl(acl)">
						{{ t('deck', 'Can manage') }}
					</ActionCheckbox>
					<ActionButton v-if="canManage" icon="icon-delete" @click="clickDeleteAcl(acl)">
						{{ t('deck', 'Delete') }}
					</ActionButton>
				</Actions>
			</li>
		</ul>

		<CollectionList v-if="board.id"
			:id="`${board.id}`"
			:name="board.title"
			type="deck" />
	</div>
</template>

<script>
import { Avatar, Multiselect, Actions, ActionButton, ActionCheckbox } from '@nextcloud/vue'
import { CollectionList } from 'nextcloud-vue-collections'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'SharingTabSidebar',
	components: {
		Avatar,
		Actions,
		ActionButton,
		ActionCheckbox,
		Multiselect,
		CollectionList,
	},
	props: {
		board: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			isLoading: false,
			addAcl: null,
			addAclForAPI: null,
		}
	},
	computed: {
		...mapState([
			'sharees',
		]),
		...mapGetters([
			'canEdit',
			'canManage',
			'canShare',
		]),
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		formatedSharees() {
			return this.unallocatedSharees.map(item => {

				const sharee = {
					user: item.label,
					displayName: item.label,
					icon: 'icon-user',
				}

				if (item.value.shareType === 1) {
					sharee.icon = 'icon-group'
					sharee.isNoUser = true
				}
				if (item.value.shareType === 7) {
					sharee.icon = 'icon-circles'
					sharee.isNoUser = true
				}

				sharee.value = item.value
				return sharee
			})
		},
		unallocatedSharees() {
			return this.sharees.filter((sharee) => {
				const foundIndex = this.board.acl.findIndex((acl) => {
					return acl.participant.uid === sharee.value.shareWith
				})
				if (foundIndex === -1) {
					return true
				}
				return false
			})
		},
	},
	mounted() {
		this.asyncFind('')
	},
	methods: {
		asyncFind(query) {
			this.isLoading = true
			this.$store.dispatch('loadSharees', query).then(response => {
				this.isLoading = false
			})
		},
		clickAddAcl() {
			this.addAclForAPI = {
				type: this.addAcl.value.shareType,
				participant: this.addAcl.value.shareWith,
				permissionEdit: false,
				permissionShare: false,
				permissionManage: false,
			}
			this.$store.dispatch('addAclToCurrentBoard', this.addAclForAPI)
		},
		clickEditAcl(acl) {
			this.addAclForAPI = Object.assign({}, acl)
			this.addAclForAPI.permissionEdit = !acl.permissionEdit
			this.$store.dispatch('updateAclFromCurrentBoard', this.addAclForAPI)
		},
		clickShareAcl(acl) {
			this.addAclForAPI = Object.assign({}, acl)
			this.addAclForAPI.permissionShare = !acl.permissionShare
			this.$store.dispatch('updateAclFromCurrentBoard', this.addAclForAPI)
		},
		clickManageAcl(acl) {
			this.addAclForAPI = Object.assign({}, acl)
			this.addAclForAPI.permissionManage = !acl.permissionManage
			this.$store.dispatch('updateAclFromCurrentBoard', this.addAclForAPI)
		},
		clickDeleteAcl(acl) {
			this.$store.dispatch('deleteAclFromCurrentBoard', acl)
		},
	},
}
</script>
<style scoped>
	#shareWithList {
		margin-bottom: 20px;
	}
	#shareWithList li {
		display: flex;
		align-items: center;
	}
	.username {
		padding: 12px 9px;
		flex-grow: 1;
	}
	.board-owner-label {
		opacity: .7;
	}
	.avatarLabel {
		padding: 6px
	}
	.avatardiv {
		background-color: #f5f5f5;
		border-radius: 16px;
		width: 32px;
		height: 32px;
	}
</style>
