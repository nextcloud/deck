<!--
* @copyright Copyright (c) 2018 Michael Weimann <mail@michael-weimann.eu>
*
* @author Michael Weimann <mail@michael-weimann.eu>
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
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
-->

<template>
	<!-- <router-link :id="`board-${board.id}`"
		:title="board.title"
		:to="routeTo" class="board-list-row" tag="div">
		<div class="board-list-bullet-cell">
			<div :style="{ 'background-color': `#${board.color}` }" class="board-list-bullet" />
		</div>
		<div class="board-list-title-cell">{{ board.title }}</div>
		<div class="board-list-avatars-cell">
			<avatar :user="board.owner.uid" class="board-list-avatar" />
			<avatar v-for="user in limitedAcl" :key="user.id" :user="user.participant.uid"
				class="board-list-avatar" />
			<div v-tooltip="otherAcl" v-if="board.acl.length > 5" class="avatardiv popovermenu-wrapper board-list-avatar icon-more" />
		</div>
		<div class="board-list-actions-cell" />
	</router-link> -->

	<div class="card-list-row">
		<div class="card-list-bullet-cell">
			<input id="card-status" type="checkbox" class="checkbox">
			<label for="card-status" />
		</div>
		<div class="card-list-bullet-cell">
			<div :style="{ 'background-color': `#990` }" class="card-list-bullet" />
		</div>
		<div class="card-list-title-cell">{{ card.title }}</div>

		<div>
			<ul class="labels card-list-title-cell">
				<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)"><span>{{ label.title }}</span></li>
			</ul>
		</div>

		<div>
			<div v-if="card.attachments" class="card-files icon icon-files-dark" />
			<div v-if="card.duedate"><span>{{ dueTime(card.duetime) }}</span></div>
		</div>

		<avatar-list :users="card.assignedUsers" class="card-list-avatars-cell" />
		<div class="card-list-actions-cell">

			<Actions v-if="!editing" @click.stop.prevent>
				<ActionButton icon="icon-user">{{ t('deck', 'Assign to me') }}</ActionButton>

				<ActionButton icon="icon-delete">{{ t('deck', 'Delete card') }}</ActionButton>
				<ActionButton icon="icon-external">{{ t('deck', 'Move card') }}</ActionButton>
				<ActionButton icon="icon-settings-dark">{{ t('deck', 'Card details') }}</ActionButton>
			</Actions>
		</div>
	</div>
</template>

<script>
import { Avatar } from 'nextcloud-vue'
import Color from '../mixins/color'
import AvatarList from './cards/AvatarList'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'

export default {
	name: 'CardItem',
	components: {
		Avatar,
		AvatarList,
		Actions,
		ActionButton
	},
	mixins: [Color],
	props: {
		card: {
			type: Object,
			default: () => { return {} }
		}
	},
	computed: {
		labelStyle() {
			return (label) => {
				return {
					backgroundColor: '#' + label.color,
					color: this.textColor(label.color)
				}
			}
		}

	},
	methods: {
		dueTime(due) {
			return OC.Util.relativeModifiedDate(due)
		}
	}
}
</script>

<style lang="scss" scoped>

$card-spacing: 20px;
	$card-padding: 15px;

	.labels {
		flex-grow: 1;
		flex-shrink: 1;
		min-width: 0;
		display: flex;
		flex-direction: row;
		margin-left: $card-padding;
		margin-right: $card-padding;
		margin-top: -5px;

		li {
			flex-grow: 0;
			flex-shrink: 1;
			display: flex;
			flex-direction: row;
			overflow: hidden;
			padding: 1px 3px;
			border-radius: 3px;
			font-size: 85%;
			margin-right: 3px;
			margin-bottom: 3px;

			&:hover {
				overflow: unset;
			}

			span {
				flex-grow: 0;
				flex-shrink: 1;
				min-width: 0;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		}
	}

	.card-list-bullet-cell {
		padding: 6px 15px;

		.card-list-bullet {
			border-radius: 50%;
			cursor: pointer;
			height: 32px;
			width: 32px;
		}
	}

	.card-list-title-cell {
		padding: 0 15px;
	}

	.card-list-avatars-cell {
		display: flex;
		padding: 6px 15px;

		.card-list-avatar {
			border-radius: 50%;
			height: 32px;
			width: 32px;
			margin-left: 3px;
			&.icon-more {
				background-color:var(--color-background-dark);
			}
		}
	}

</style>
