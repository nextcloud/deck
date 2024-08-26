<!--
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 * @copyright Copyright (c) 2022, chandi Langecker (git@chandi.it)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * -->

<template>
	<div :title="t('deck', 'Currently present people')"
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
