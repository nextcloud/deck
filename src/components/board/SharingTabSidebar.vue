<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcSelect v-if="canShare"
			v-model="addAcl"
			:input-label="t('deck', 'Share board with a user, group or team …')"
			:options="formatedSharees"
			:user-select="true"
			label="displayName"
			:loading="isLoading || !!isSearching"
			:disabled="isLoading"
			track-by="multiselectKey"
			:internal-search="false"
			@input="clickAddAcl"
			@search="(search, loading) => asyncFind(search, loading)">
			<template #noOptions>
				{{ isSearching ? t('deck', 'Searching for users, groups and teams …') : t('deck', 'No participants found') }}
			</template>
			<template #noResult>
				{{ isSearching ? t('deck', 'Searching for users, groups and teams …') : t('deck', 'No participants found') }}
			</template>
		</NcSelect>

		<ul id="shareWithList"
			class="shareWithList">
			<li>
				<NcAvatar :user="board.owner.uid" />
				<span class="username">
					{{ board.owner.displayname }}
					<span v-if="!isCurrentUser(board.owner.uid)" class="board-owner-label">
						{{ t('deck', 'Board owner') }}
					</span>
				</span>
			</li>
			<li v-for="acl in board.acl" :key="acl.id" :data-cy="'acl-participant:' + acl.participant.uid">
				<NcAvatar v-if="acl.type===0" :user="acl.participant.uid" />
				<div v-if="acl.type===1" class="avatardiv icon icon-group" />
				<div v-if="acl.type===7" class="avatardiv icon icon-circles" />
				<span class="username">
					{{ acl.participant.displayname }}
					<span v-if="acl.type===1">{{ t('deck', '(Group)') }}</span>
					<span v-if="acl.type===7">{{ t('deck', '(Team)') }}</span>
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
					<NcActionCheckbox v-if="acl.type === 0 && isCurrentUser(board.owner.uid)"
						:checked="acl.owner"
						data-cy="action:permission-owner"
						@change="clickTransferOwner(acl.participant.uid)">
						{{ t('deck', 'Owner') }}
					</NcActionCheckbox>
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

		<CollectionList v-if="projectsEnabled && board.id"
			:id="`${board.id}`"
			:name="board.title"
			type="deck" />
	</div>
</template>

<script>
import { NcAvatar, NcSelect, NcActions, NcActionButton, NcActionCheckbox, NcRelatedResourcesPanel } from '@nextcloud/vue'
import { CollectionList } from 'nextcloud-vue-collections'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import debounce from 'lodash/debounce.js'

export default {
	name: 'SharingTabSidebar',
	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
		NcActionCheckbox,
		NcSelect,
		CollectionList,
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
				const subname = item.label === item.shareWithDisplayNameUnique
					? ''
					: item.shareWithDisplayNameUnique
				const sharee = {
					user: item.value.shareWith,
					displayName: item.label,
					subname,
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
		this.asyncFind('', () => {})
	},
	methods: {
		debouncedFind: debounce(async function(query) {
			this.isSearching = true
			await this.$store.dispatch('loadSharees', query)
			this.isSearching = false
		}, 300),
		async asyncFind(query, loading) {
			loading(true)
			await this.debouncedFind(query)
			loading(false)
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
