<template>
		<div>
			<multiselect :options="board.sharees" />

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
				</li>
			</ul>
		</div>
</template>

<script>
import { Avatar, Multiselect } from 'nextcloud-vue'

export default {
	name: 'SharingTabSidebard',
	components: {
		Avatar,
		Multiselect,
	},
	props: {
	},
	data() {
		return {
		}
	},
	props: {
		board: {
			type: Object,
			default: undefined
		}
	}
	
	
	
}
</script>
