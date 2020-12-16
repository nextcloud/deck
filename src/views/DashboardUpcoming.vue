<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
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
	<div>
		<NcDashboardWidget :items="cards"
			empty-content-icon="icon-deck"
			:empty-content-message="t('deck', 'No upcoming cards')"
			:show-more-text="t('deck', 'upcoming cards')"
			:loading="loading"
			@hide="() => {}"
			@markDone="() => {}">
			<template #default="{ item }">
				<a :key="item.id"
					:href="cardLink(item)"
					target="_blank"
					class="card">
					<div class="card--header">
						<DueDate class="right" :card="item" />
						<span class="title">{{ item.title }}</span>
					</div>
					<ul v-if="item.labels && item.labels.length"
						class="labels">
						<li v-for="label in item.labels" :key="label.id" :style="labelStyle(label)">
							<span>{{ label.title }}</span>
						</li>
					</ul>
				</a>
			</template>
		</NcDashboardWidget>
		<div class="center-button">
			<NcButton @click="toggleAddCardModel">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('deck', 'New card') }}
			</NcButton>
			<NcModal v-if="showAddCardModal" class="card-selector" @close="toggleAddCardModel">
				<CreateNewCardCustomPicker show-created-notice @cancel="toggleAddCardModel" />
			</NcModal>
		</div>
	</div>
</template>

<script>
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { NcButton, NcDashboardWidget, NcModal } from '@nextcloud/vue'
import { mapGetters } from 'vuex'
import labelStyle from './../mixins/labelStyle.js'
import DueDate from '../components/cards/badges/DueDate.vue'
import { generateUrl } from '@nextcloud/router'
import CreateNewCardCustomPicker from './CreateNewCardCustomPicker.vue'

export default {
	name: 'DashboardUpcoming',
	components: {
		CreateNewCardCustomPicker,
		NcModal,
		DueDate,
		NcDashboardWidget,
		NcButton,
		PlusIcon,
	},
	mixins: [labelStyle],
	data() {
		return {
			loading: false,
			showAddCardModal: false,
		}
	},
	computed: {
		...mapGetters([
			'assignedCardsDashboard',
		]),
		cards() {
			const list = [
				...this.assignedCardsDashboard,
			].filter((card) => {
				return card.duedate !== null
			})
			list.sort((a, b) => {
				return (new Date(a.duedate)).getTime() - (new Date(b.duedate)).getTime()
			})
			return list.slice(0, 5)
		},
		cardLink() {
			return (card) => {
				return generateUrl('/apps/deck') + `#/board/${card.boardId}/card/${card.id}`
			}
		},
		showMoreUrl() {
			return this.cards.length > 7 ? generateUrl('/apps/deck') : null
		},
	},
	beforeMount() {
		this.loading = true
		this.$store.dispatch('loadUpcoming').then(() => {
			this.loading = false
		})
	},
	methods: {
		toggleAddCardModel() {
			this.showAddCardModal = !this.showAddCardModal
		},
	},
}
</script>

<style lang="scss" scoped>
	@import './../css/labels';

	.center-button {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-top: 10px;
	}

	#deck-widget-empty-content {
		text-align: center;
		margin-top: 5vh;
	}

	.card {
		display: block;
		border-radius: var(--border-radius-large);
		padding: 5px 8px;
		height: 70px;
		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.card--header {
		overflow: hidden;
		.title {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			display: block;
			position: relative;
			top: 3px;
		}
	}

	.labels {
		margin-left: 0;
		margin-top: 3px;
	}

	.duedate:deep {
		.due {
			margin: 0 0 0 10px;
			padding: 0px 4px;
			font-size: 90%;
			margin-bottom: 7px;
		}
	}

	.right {
		float: right;
	}
</style>
