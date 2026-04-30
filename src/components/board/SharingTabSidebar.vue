<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcSelectUsers v-model="addAcl"
			:options="formatedSharees"
			:loading="isLoading"
			@search="(search) => asyncFind(search)" />

		<ul id="shareWithList"
			class="shareWithList">
			<li>
				<NcAvatar v-if="board.ownerType !== PERMISSION_TYPE_CIRCLE" :user="board.owner.uid" />
				<div v-else class="avatardiv icon icon-circles" />
				<span class="username">
					{{ board.owner.displayname }}
					<span v-if="board.ownerType !== PERMISSION_TYPE_CIRCLE && !isCurrentUser(board.owner.uid)" class="board-owner-label">
						{{ t('deck', 'Board owner') }}
					</span>
					<span v-if="board.ownerType === PERMISSION_TYPE_CIRCLE" class="board-owner-label">
						{{ t('deck', 'Team') }}
					</span>
				</span>
			</li>
			<li v-for="acl in board.acl" :key="acl.id" :data-cy="'acl-participant:' + acl.participant.uid">
				<NcAvatar v-if="acl.type===0" :user="acl.participant.uid" />
				<div v-if="acl.type===1" class="avatardiv icon icon-group" />
				<div v-if="acl.type===7" class="avatardiv icon icon-circles" />
				<div v-if="acl.type===6" class="avatardiv icon" />
				<span class="username">
					{{ acl.participant.displayname || acl.participant }}
					<span v-if="acl.type===1">{{ t('deck', '(Group)') }}</span>
					<span v-if="acl.type===7">{{ t('deck', '(Team)') }}</span>
					<span v-if="acl.type===6">{{ t('deck', '(remote)') }}</span>
				</span>

				<NcActionCheckbox v-if="!(isCurrentUser(acl.participant.uid) && acl.type === 0) && (canManage || (canEdit && canShare))"
					:checked="acl.permissionEdit"
					data-cy="action:permission-edit"
					@change="clickEditAcl(acl)">
					{{ t('deck', 'Can edit') }}
				</NcActionCheckbox>
				<NcActions v-if="!(isCurrentUser(acl.participant.uid) && acl.type === 0)" :force-menu="true">
					<NcActionCheckbox v-if="canManage || canShare"
						:checked="acl.permissionShare"
						data-cy="action:permission-share"
						@change="clickShareAcl(acl)">
						{{ t('deck', 'Can share') }}
					</NcActionCheckbox>
					<NcActionCheckbox v-if="canManage"
						:checked="acl.permissionManage"
						data-cy="action:permission-manage"
						@change="clickManageAcl(acl)">
						{{ t('deck', 'Can manage') }}
					</NcActionCheckbox>
					<NcActionButton v-if="canTransferTo(acl)"
						icon="icon-transfer-ownership"
						data-cy="action:permission-owner"
						@click="clickTransferOwner(acl.participant.uid || acl.participant.id, acl.type)">
						{{ t('deck', 'Transfer ownership') }}
					</NcActionButton>
					<NcActionButton v-if="canManage"
						icon="icon-delete"
						data-cy="action:acl-delete"
						@click="clickDeleteAcl(acl)">
						{{ t('deck', 'Delete') }}
					</NcActionButton>
				</NcActions>
			</li>
		</ul>

		<NcRelatedResourcesPanel v-if="board.id"
			provider-id="deck"
			:item-id="board.id" />

		<NcCollectionList v-if="projectsEnabled && board.id"
			:id="`${board.id}`"
			:name="board.title"
			type="deck" />
	</div>
</template>

<script>
import { NcCollectionList, NcAvatar, NcActions, NcActionButton, NcActionCheckbox, NcRelatedResourcesPanel, NcSelectUsers } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import debounce from 'lodash/debounce.js'
import { PERMISSION_TYPE_CIRCLE, PERMISSION_TYPE_USER, SOURCE_TO_SHARE_TYPE } from '../../helpers/constants.js'

export default {
	name: 'SharingTabSidebar',
	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcSelectUsers,
		NcCollectionList,
		NcRelatedResourcesPanel,
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
			projectsEnabled: loadState('core', 'projects_enabled', false),
			// Expose ACL type constants to the template
			PERMISSION_TYPE_CIRCLE,
			PERMISSION_TYPE_USER,
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
		canTransferTo() {
			return (acl) => {
				// For user-owned boards: only the current owner can transfer, and only to users
				if (this.board.ownerType !== PERMISSION_TYPE_CIRCLE) {
					return acl.type === PERMISSION_TYPE_USER && this.isCurrentUser(this.board.owner.uid)
				}
				// For circle-owned boards: any circle member with manage rights can transfer to any participant
				return this.canManage
			}
		},
		formatedSharees() {
			const result = this.unallocatedSharees.map(item => {
				const res = {
					...item,
					displayName: item.displayname || item.name || item.label || item.id,
					user: item.id,
					subname: item.shareWithDisplayNameUnique || item.subline || item.id, // NcSelectUser does its own pattern matching to filter things out
					type: SOURCE_TO_SHARE_TYPE[item.source],
				}
				return res
			}).slice(0, 10)
			return result
		},
		unallocatedSharees() {
			return this.sharees.filter((sharee) => {
				const foundIndex = this.board.acl.findIndex((acl) => {
					if (acl.participant.uid === sharee.id && acl.type === SOURCE_TO_SHARE_TYPE[sharee.source]) {
						return true
					}
					if (acl.participant.id === sharee.id && acl.type === SOURCE_TO_SHARE_TYPE[sharee.source]) {
						return true
					}
					return false
				})
				if (foundIndex === -1) {
					return true
				}
				return false
			})
		},
	},
	watch: {
		addAcl: {
			handler() {
				if (this.addAcl) {
					this.clickAddAcl()
				}
			},
		},
	},
	mounted() {
		this.asyncFind('', () => {})
	},
	methods: {
		debouncedFind: debounce(async function(query) {
			this.isSearching = true
			await this.$store.dispatch('loadSharees', query)
			this.isSearching = false
		}, 300),
		async asyncFind(query) {
			this.isLoading = true
			await this.debouncedFind(query)
			this.isLoading = false
		},
		async clickAddAcl() {
			this.addAclForAPI = {
				type: SOURCE_TO_SHARE_TYPE[this.addAcl.source],
				participant: this.addAcl.id,
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
		clickTransferOwner(newOwner, newOwnerType) {
			const targetLabel = newOwnerType === PERMISSION_TYPE_CIRCLE
				? t('deck', 'team {name}', { name: newOwner })
				: newOwner
			OC.dialogs.confirmDestructive(
				t('deck', 'Are you sure you want to transfer the board {title} to {target}?', { title: this.board.title, target: targetLabel }),
				t('deck', 'Transfer the board'),
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
								newOwnerType,
							})
							const successMessage = t('deck', 'The board has been transferred to {target}', { target: targetLabel })
							showSuccess(successMessage)
							this.$router.push({ name: 'main' })
						} catch (e) {
							const errorMessage = t('deck', 'Failed to transfer the board to {target}', { target: targetLabel })
							showError(errorMessage)
						} finally {
							this.isLoading = false
						}
					}
				},
				true,
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
