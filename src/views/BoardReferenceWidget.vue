<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author 2023 Julien Veyssier <julien-nc@posteo.net>
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
  -->

<template>
	<div v-if="interactive" class="deck-board-reference-interactive">
		<Board :id="board.id" />
	</div>
	<div v-else class="deck-board-reference">
		<div class="line">
			<DeckIcon :size="20" class="title-icon" />
			<strong>
				<a :href="boardLink"
					:title="boardTooltip"
					target="_blank"
					class="link">
					{{ board.title }}
				</a>
			</strong>
		</div>
		<div class="line">
			{{ t('deck', 'Owner') + ': ' }}
			<NcUserBubble :user="boardOwnerUserId"
				:display-name="boardOwnerDisplayName" />
		</div>
	</div>
</template>

<script>
import Board from '../components/board/Board.vue'
import DeckIcon from '../components/icons/DeckIcon.vue'
import { BoardApi } from './../services/BoardApi.js'
import store from './../store/main.js'

import NcUserBubble from '@nextcloud/vue/dist/Components/NcUserBubble.js'

import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

const boardApi = new BoardApi()

export default {
	name: 'BoardReferenceWidget',

	store,

	components: {
		DeckIcon,
		NcUserBubble,
		Board,
	},

	provide() {
		return {
			boardApi,
		}
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
		interactive: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		board() {
			return this.richObject.board
		},
		boardLink() {
			return generateUrl('/apps/deck/#/board/{boardId}', { boardId: this.board.id })
		},
		boardTooltip() {
			return t('deck', 'Deck board {name}\n* Last modified on {lastMod}', {
				name: this.board.title,
				lastMod: moment.unix(this.board.lastModified).format('LLL'),
			})
		},
		boardOwnerUserId() {
			return this.board.owner?.uid ?? '???'
		},
		boardOwnerDisplayName() {
			return this.board.owner?.displayname ?? this.boardOwnerUserId
		},
	},

	created() {
		this.$store.commit('setFullApp', false)
	},

}
</script>

<style scoped lang="scss">
.deck-board-reference {
	width: 100%;
	// needed for the specific case of Text
	.editor__content & {
		width: calc(100% - 24px);
	}
	white-space: normal;
	padding: 12px;

	.link {
		text-decoration: underline;
		color: var(--color-main-text) !important;
		padding: 0 !important;
	}

	.line {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
		.title-icon {
			margin-right: 8px;
		}
	}
}

.deck-board-reference-interactive {
	width: 100%;
	height: 100%;
	min-height: min(200px, 100vh);
	&:deep(.controls) {
		padding-left: 12px;
	}
	&:deep(.board) {
		padding-left: 0;
	}
	&:deep(*) {
		-webkit-box-sizing: border-box; /* Safari/Chrome, other WebKit */
		-moz-box-sizing: border-box;    /* Firefox, other Gecko */
		box-sizing: border-box;         /* Opera/IE 8+ */
	}
}
</style>
