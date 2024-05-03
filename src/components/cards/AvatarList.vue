<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="avatars">
		<div>
			<NcPopover>
				<template #trigger="{ attrs }">
					<button class="avatar-list" v-bind="attrs" @click.stop>
						<div v-if="popover.length > 0">
							<AccountMultiple class="avatardiv more-avatars" :size="24" />
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
								:tooltip-message="user.participant.displayname + ' ' + t('deck', '(Team)')"
								:is-no-user="true"
								:disable-="true"
								:size="32" />
						</div>
					</button>
				</template>
				<div>
					<div v-for="user in users"
						:key="user.id"
						class="avatar-list-entry">
						<NcAvatar :user="user.participant.uid"
							:display-name="user.participant.displayname"
							:disable-menu="true"
							:is-no-user="user.type !== 0"
							:size="32" />
						<div class="avatar-list-entry__label">
							{{ user.participant.displayname }}
						</div>
					</div>
				</div>
			</NcPopover>
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
import { NcAvatar, NcPopover } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'

export default {
	name: 'AvatarList',
	components: {
		NcAvatar,
		NcPopover,
		AccountMultiple,
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
		position: relative;
		flex-grow: 1;
		:deep(.popovermenu) {
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
		flex-direction: row-reverse;
		padding: 0;
		padding-right: $avatar-offset;
		margin: 0;
		border: 0;
		background: transparent;

		& > div {
			height: 32px;
		}

		.avatardiv,
		:deep(.avatardiv) {
			width: 32px;
			height: 32px;
			box-sizing: content-box !important;
			margin-right: -$avatar-offset;
			transition: margin-right 0.2s ease-in-out;
			border: 2px solid var(--color-main-background);
		}

		.more-avatars {
			width: 32px;
			height: 32px;
			background-color: var(--color-background-dark) !important;
			cursor: pointer;
			color: var(--color-text-maxcontrast);
		}

	}

	.avatar-list-entry {
		display: flex;
		padding: 6px 12px;

		&__label {
			padding: 4px 12px;
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
