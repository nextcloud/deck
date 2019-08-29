<template>
	<div>
		<!-- <multiselect v-model="addAcl" :options="unallocatedSharees" label="label"
			@input="clickAddAcl" @search-change="asyncFind">
			<template #option="scope">
				<avatar :user="scope.option.value.shareWith" />
				<span class="avatarLabel">{{ scope.option.label }} </span>
			</template>
		</multiselect> -->

		<multiselect
			v-model="addAcl"
			:options="formatedSharees"
			:user-select="true"
			label="displayName"
			track-by="user"
			@input="clickAddAcl"
			@search-change="asyncFind" />

		<ul
			id="shareWithList"
			class="shareWithList"
		>
			<li>
				<avatar :user="board.owner.uid" />
				<span class="has-tooltip username">
					{{ board.owner.displayname }}
				</span>
			</li>
			<li v-for="acl in board.acl" :key="acl.participant.uid">
				<avatar :user="acl.participant.uid" />
				<span class="has-tooltip username">
					{{ acl.participant.displayname }}
				</span>

				<Actions>
					<ActionCheckbox :checked="acl.permissionEdit" @change="clickEditAcl(acl)">{{ t('deck', 'Can edit') }}</ActionCheckbox>
				</Actions>
				<Actions>
					<ActionCheckbox :checked="acl.permissionShare" @change="clickShareAcl(acl)">{{ t('deck', 'Can share') }}</ActionCheckbox>
					<ActionCheckbox :checked="acl.permissionManage" @change="clickManageAcl(acl)">{{ t('deck', 'Can manage') }}</ActionCheckbox>
					<ActionButton icon="icon-delete" @click="clickDeleteAcl(acl)">{{ t('deck', 'Delete') }}</ActionButton>
				</Actions>
			</li>
		</ul>

		<collection-list v-if="board.id" :id="`${board.id}`" :name="board.title"
			type="deck" />
	</div>
</template>

<script>
import { Avatar, Multiselect, Actions, ActionButton, ActionCheckbox } from 'nextcloud-vue'
import { CollectionList } from 'nextcloud-vue-collections'
import { mapGetters } from 'vuex'

export default {
	name: 'SharingTabSidebard',
	components: {
		Avatar,
		Actions,
		ActionButton,
		ActionCheckbox,
		Multiselect,
		CollectionList
	},
	props: {
		board: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			isLoading: false,
			addAcl: null,
			addAclForAPI: null
		}
	},
	computed: {
		...mapGetters({
			sharees: 'sharees'
		}),
		formatedSharees() {
			return this.unallocatedSharees.map(item => {
				return {
					user: item.label,
					displayName: item.label,
					// desc: 'desc',
					icon: item.value.shareType === 1 ? 'icon-group' : 'icon-user',
					isNoUser: item.value.shareType === 1
				}
			})
		},
		unallocatedSharees() {
			return this.sharees.filter((sharee) => {
				return Object.values(this.board.acl).findIndex((acl) => {
					return acl.participant.uid === sharee.value.shareWith
				})
			})
		}
	},
	methods: {
		asyncFind(query) {
			this.isLoading = true
			this.$store.dispatch('loadSharees').then(response => {
				this.isLoading = false
			})
		},
		clickAddAcl() {
			this.addAclForAPI = {
				type: this.addAcl.value.shareType,
				participant: this.addAcl.value.shareWith,
				permissionEdit: false,
				permissionShare: false,
				permissionManage: false
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
		}
	}
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
	.avatarLabel {
		padding: 6px
	}
</style>
