<template>
	<div>
		<multiselect v-model="addAcl" :options="unallocatedSharees" label="label"
			@input="clickAddAcl" @search-change="asyncFind">
			<template #option="scope">
				{{ scope.option.label }}
			</template>
		</multiselect>

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

				<input :checked="acl.permissionEdit" type="checkbox" @click="clickEditAcl(acl)">
				<label for="checkbox">{{ t('deck', 'Edit') }}</label>

				<input :checked="acl.permissionShare" type="checkbox" @click="clickShareAcl(acl)">
				<label for="checkbox">{{ t('deck', 'Share') }}</label>

				<input :checked="acl.permissionManage" type="checkbox" @click="clickManageAcl(acl)">
				<label for="checkbox">{{ t('deck', 'Manage') }}</label>

				<button v-tooltip="t('deck', 'Delete')" class="icon-delete" @click="clickDeleteAcl(acl)" />
			</li>
		</ul>

		<collection-list v-if="board.id" :id="&quot;board.id&quot;" :name="board.title"
			type="deck" />
	</div>
</template>

<script>
import { Avatar, Multiselect } from 'nextcloud-vue'
import { CollectionList } from 'nextcloud-vue-collections'
import { mapGetters } from 'vuex'

export default {
	name: 'SharingTabSidebard',
	components: {
		Avatar,
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
		unallocatedSharees() {
			let ret = []

			let allocatedSharees = []
			for (var user in this.board.acl) {
				allocatedSharees.push(this.board.acl[user].participant.uid)
			}

			this.sharees.forEach(function(sharee) {
				if (allocatedSharees.indexOf(sharee.value.shareWith) === -1) {
					ret.push(sharee)
				}
			})

			return ret
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
				type: 0,
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
