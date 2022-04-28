<template>
	<div>
		<Multiselect
			v-if="canShare"
			v-model="addAcl"
			:placeholder="t('deck', 'Share board with a user, group or circle …')"
			:options="formatedSharees"
			:user-select="true"
			label="displayName"
			:loading="isLoading || !!isSearching"
			:disabled="isLoading"
			track-by="multiselectKey"
			:internal-search="false"
			@input="clickAddAcl"
			@search-change="asyncFind">
			<template #noOptions>
				{{ isSearching ? t('deck', 'Searching for users, groups and circles …') : t('deck', 'No participants found') }}
			</template>
			<template #noResult>
				{{ isSearching ? t('deck', 'Searching for users, groups and circles …') : t('deck', 'No participants found') }}
			</template>
		</Multiselect>

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
			<li v-for="acl in board.acl" :key="acl.id">
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
					<ActionCheckbox v-if="acl.type === 0 && isCurrentUser(board.owner.uid)" :checked="acl.owner" @change="clickTransferOwner(acl.participant.uid)">
						{{ t('deck', 'Owner') }}
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import debounce from 'lodash/debounce'

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
			isSearching: false,
			addAcl: null,
			addAclForAPI: null,
			newOwner: null,
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
					user: item.value.shareWith,
					displayName: item.label,
					icon: 'icon-user',
					multiselectKey: item.shareType + ':' + item.primaryKey,
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
			}).slice(0, 10)
		},
		unallocatedSharees() {
			return this.sharees.filter((sharee) => {
				const foundIndex = this.board.acl.findIndex((acl) => {
					return acl.participant.uid === sharee.value.shareWith && acl.participant.type === sharee.value.shareType
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
		debouncedFind: debounce(async function(query) {
			this.isSearching = true
			await this.$store.dispatch('loadSharees', query)
			this.isSearching = false
		}, 300),
		async asyncFind(query) {
			await this.debouncedFind(query)
		},
		async clickAddAcl() {
			this.addAclForAPI = {
				type: this.addAcl.value.shareType,
				participant: this.addAcl.value.shareWith,
				permissionEdit: false,
				permissionShare: false,
				permissionManage: false,
			}
			this.isLoading = true
			try {
				await this.$store.dispatch('addAclToCurrentBoard', this.addAclForAPI)
			} catch (e) {
				const errorMessage = t('deck', 'Failed to create share with {displayName}', { displayName: this.addAcl.displayName })
				console.error(errorMessage, e)
				showError(errorMessage)
			}
			this.addAcl = null
			this.isLoading = false
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
		clickTransferOwner(newOwner) {
			OC.dialogs.confirmDestructive(
				t('deck', 'Are you sure you want to transfer the board {title} to {user}?', { title: this.board.title, user: newOwner }),
				t('deck', 'Transfer the board.'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('deck', 'Transfer'),
					confirmClasses: 'error',
					cancel: t('deck', 'Cancel'),
				},
				async (result) => {
					if (result) {
						try {
							this.isLoading = true
							await this.$store.dispatch('transferOwnership', {
								boardId: this.board.id,
								newOwner,
							})
							const successMessage = t('deck', 'The board has been transferred to {user}', { user: newOwner })
							showSuccess(successMessage)
							this.$router.push({ name: 'main' })
						} catch (e) {
							const errorMessage = t('deck', 'Failed to transfer the board to {user}', { user: newOwner.user })
							showError(errorMessage)
						} finally {
							this.isLoading = false
						}
					}
				},
				true
			)
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
		background-color: var(--color-background-dark);
		border-radius: 16px;
		width: 32px;
		height: 32px;
	}
</style>
