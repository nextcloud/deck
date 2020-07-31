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
		<div class="avatar-list" @click.stop="togglePopover">
			<div v-if="popover.length > 0">
				<div class="avatardiv icon-more" />
			</div>
			<div v-for="user in firstUsers" :key="user.id">
				<Avatar v-if="user.type === 0"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:disable-menu="true"
					:size="32" />
				<Avatar v-if="user.type === 1"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:tooltip-message="user.participant.displayname + ' ' + t('deck', '(group)')"
					:is-no-user="true"
					:disable-="true"
					:size="32" />
				<Avatar v-if="user.type === 7"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:tooltip-message="user.participant.displayname + ' ' + t('deck', '(circle)')"
					:is-no-user="true"
					:disable-="true"
					:size="32" />
			</div>
		</div>

		<div v-show="popoverVisible" class="popovermenu menu-right">
			<PopoverMenu :menu="popover" />
			<slot />
		</div>
	</div>
</template>

<script>
import { Avatar, PopoverMenu, Tooltip } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'AvatarList',
	components: {
		Avatar,
		PopoverMenu,
	},
	directives: {
		tooltip: Tooltip,
	},
	props: {
		users: {
			type: Array,
			default: () => { return {} },
		},
	},
	data() {
		return {
			popoverVisible: false,
		}
	},
	computed: {
		firstUsers() {
			if (!this.users || this.users.length === 0) {
				return []
			}
			return this.users.slice(0, 3)
		},
		avatarUrl() {
			return (assignable) => {
				if (assignable.type === 1) {
					return 'icon-group'
				}
				const user = assignable.participant.uid
				const size = 32
				const avatarUrl = generateUrl('/avatar/{user}/{size}',
					{
						user,
						size,
					})
				return window.location.protocol + '//' + window.location.host + avatarUrl
			}
		},
		popover() {
			if (!this.users || this.users.length === 0) {
				return []
			}
			return [
				...this.users.slice(3).map((session) => {
					return {
						href: '#',
						icon: this.avatarUrl(session),
						text: session.participant.displayname + (session.type === 1 ? ' ' + t('deck', '(group)') : ''),
					}
				}),
			]
		},
	},
	methods: {
		togglePopover() {
			if (this.popover.length > 0) {
				this.popoverVisible = !this.popoverVisible
			}
		},
	},
}
</script>

<style scoped lang="scss">
	.avatars {
		margin-top: 5px;
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
		padding-right: 8px;
		flex-direction: row-reverse;
		.avatardiv,
		/deep/ .avatardiv {
			width: 36px;
			height: 36px;
			box-sizing: content-box !important;
			&.icon-more {
				width: 32px;
				height: 32px;
				opacity: .5;
				background-color: var(--color-background-dark) !important;
				cursor: pointer;
			}
			& {
				margin-right: -12px;
				transition: margin-right 0.2s ease-in-out;
			}
		}
		&:hover div:nth-child(n+2) /deep/ .avatardiv {
			margin-right: 1px;
		}
	}
	.popovermenu {
		display: block;
		margin: 40px -6px;
	}
</style>
