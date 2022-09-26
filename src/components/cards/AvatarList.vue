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
				<NcAvatar v-if="user.type === 0"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:disable-menu="true"
					:show-user-status="false"
					:size="32" />
				<NcAvatar v-if="user.type === 1"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:tooltip-message="user.participant.displayname + ' ' + t('deck', '(Group)')"
					:is-no-user="true"
					:disable-="true"
					:size="32" />
				<NcAvatar v-if="user.type === 7"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:tooltip-message="user.participant.displayname + ' ' + t('deck', '(Circle)')"
					:is-no-user="true"
					:disable-="true"
					:size="32" />
			</div>
		</div>

		<div v-show="popoverVisible" class="popovermenu menu-right">
			<NcPopoverMenu :menu="popover" />
			<slot />
		</div>

		<div class="avatar-print-list">
			<div v-for="user in avatarUsers" :key="user.id" class="avatar-print-list-item">
				<NcAvatar class="avatar-print-list-avatar"
					:user="user.participant.uid"
					:display-name="user.participant.displayname"
					:disable-menu="true"
					:is-no-user="user.type !== 0"
					:size="24" />
				{{ user.participant.displayname }}
			</div>
		</div>
	</div>
</template>

<script>
import { NcAvatar, NcPopoverMenu, Tooltip } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'AvatarList',
	components: {
		NcAvatar,
		NcPopoverMenu,
	},
	directives: {
		tooltip: Tooltip,
	},
	props: {
		users: {
			type: Array,
			default: () => ([]),
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
		avatarUsers() {
			if (!this.users) {
				return []
			}

			return this.users.filter((user) => {
				return [0, 1, 7].includes(user.type)
			})
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
		::v-deep .popovermenu {
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

	$avatar-offset: 12px;

	.avatar-list {
		float: right;
		display: inline-flex;
		padding-right: $avatar-offset;
		flex-direction: row-reverse;
		.avatardiv,
		::v-deep .avatardiv {
			width: 36px;
			height: 36px;
			box-sizing: content-box !important;
			margin-right: -$avatar-offset;
			transition: margin-right 0.2s ease-in-out;

			&.icon-more {
				width: 32px;
				height: 32px;
				opacity: .5;
				background-color: var(--color-background-dark) !important;
				cursor: pointer;
			}
		}
		&:hover div:nth-child(n+2) ::v-deep .avatardiv {
			margin-right: 1px;
		}
	}

	.popovermenu {
		display: block;
		margin: 40px -6px;
	}

	.avatar-print-list {
		display: none;
	}

	@media print {
		.avatar-list {
			display: none;
		}

		.avatar-print-list-item {
			align-items: center;
			display: flex;
			gap: 10px;
			margin-bottom: 10px;
		}

		.avatar-print-list {
			display: block;
			padding-top: 5px;
		}
	}
</style>
