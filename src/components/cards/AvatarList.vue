<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="avatars">
		<div class="avatar-list" @click="popoverVisible=!popoverVisible">
			<div v-if="popover.length > 0" class="avatardiv icon-more" />
			<avatar v-for="user in firstUsers" :key="user.id"
				:url="avatarUrl(user)" :disable-tooltip="true" :size="32" />
		</div>

		<div v-show="popoverVisible" class="popovermenu menu-right">
			<popover-menu :menu="popover" />
			<slot />
		</div>
	</div>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import PopoverMenu from 'nextcloud-vue/dist/Components/PopoverMenu'
import Tooltip from 'nextcloud-vue/dist/Directives/Tooltip'
export default {
	name: 'AvatarList',
	components: {
		Avatar,
		PopoverMenu
	},
	directives: {
		tooltip: Tooltip
	},
	props: {
		users: {
			type: Array,
			default: () => { return {} }
		}
	},
	data() {
		return {
			popoverVisible: false
		}
	},
	computed: {
		firstUsers() {
			return this.users.slice(0, 3)
		},
		avatarUrl() {
			return (session) => {
				const user = session.participant.displayname
				const size = 32
				const avatarUrl = OC.generateUrl('/avatar/{user}/{size}',
					{
						user: user,
						size: size
					})
				return window.location.protocol + '//' + window.location.host + avatarUrl
			}
		},
		popover() {
			return [
				...this.users.slice(3).map((session) => {
					return {
						href: '#',
						icon: this.avatarUrl(session),
						text: session.participant.displayname
					}
				})
			]
		}

	}
}
</script>

<style scoped lang="scss">
	.avatars {
		margin: 0;
		position: relative;
		flex-grow: 1;
		/deep/ .popovermenu {
			margin-right: -4px;
			img {
				padding: 0;
				width: 32px !important;
				height: 32px !important;
				margin: 6px;
				border-radius: 50%;
			}
		}
	}
	.avatar-list {
		float: right;
		display: inline-flex;
		flex-direction: row-reverse;
		.avatardiv,
		/deep/ .avatardiv {
			width: 36px;
			height: 36px;
			margin-right: -8px;
			border: 2px solid var(--color-main-background);
			background-color: var(--color-main-background) !important;
			box-sizing: content-box !important;
			&.icon-more {
				width: 32px;
				height: 32px;
				opacity: .5;
				background-color: var(--color-background-dark) !important;
				cursor: pointer;
			}
		}
	}
	.popovermenu {
		display: block;
		margin: 40px -6px;
	}
</style>
