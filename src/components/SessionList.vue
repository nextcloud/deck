<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :title="t('text', 'Currently present people')"
		class="avatar-list">
		<div v-for="session in sessionsVisible"
			:key="session.uid"
			class="avatar-wrapper"
			:style="sessionAvatarStyle">
			<NcAvatar :user="session.uid"
				:display-name="session.displayname"
				:disable-menu="true"
				:show-user-status="false"
				:disable-tooltip="true"
				:size="size" />
		</div>
	</div>
</template>

<script>
import { NcAvatar } from '@nextcloud/vue'

export default {
	name: 'SessionList',
	components: {
		NcAvatar,
	},
	props: {
		sessions: {
			type: Array,
			default: () => { return [] },
		},
		size: {
			type: Number,
			default: () => 32,
		},
	},
	computed: {
		sessionsVisible() {
			if (!this.sessions) return []
			return this.sessions.slice(0, 5)
		},
		sessionAvatarStyle() {
			return {
				'--size': this.size + 'px',
				'--font-size': this.size / 2 + 'px',
			}
		},
	},
}
</script>

<style scoped lang="scss">

.avatar-list {
	min-height: 44px;
	align-items: center;
	padding-right: 0.5em;
	border: none;
	background-color: var(--color-main-background);
	margin: 0;
	padding-left: 6px;
	display: inline-flex;
	flex-direction: row-reverse;

	&:focus {
		background-color: #eee;
	}

}

.avatar-wrapper {
	background-color: #b9b9b9;
	border-radius: 50%;
	border: 1px solid var(--color-border-dark);
	width: var(--size);
	height: var(--size);
	text-align: center;
	color: #ffffff;
	line-height: var(--size);
	font-size: var(--font-size);
	font-weight: normal;
	z-index: 1;
	overflow: hidden;
	box-sizing: content-box !important;
	margin-left: -8px;
}
</style>
