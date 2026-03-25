<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="card" class="card-menu" @click.stop.prevent>
		<NcButton v-if="card.referenceData"
			type="tertiary"
			:title="t('deck','Open link')"
			@click="openLink">
			<template #icon>
				<LinkIcon :size="20" />
			</template>
		</NcButton>
		<NcActions>
			<NcActionButton :close-after-click="true" @click="openCardFromEntries">
				<template #icon>
					<CardBulletedIcon :size="20" decorative />
				</template>
				{{ t('deck', 'Card details') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit" :close-after-click="true" @click="editTitleFromEntries">
				<template #icon>
					<PencilIcon :size="20" decorative />
				</template>
				{{ t('deck', 'Edit title') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit && !isCurrentUserAssigned"
				icon="icon-user"
				:close-after-click="true"
				@click="assignCardToMe()">
				{{ t('deck', 'Assign to me') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit && isCurrentUserAssigned"
				icon="icon-user"
				:close-after-click="true"
				@click="unassignCardFromMe()">
				{{ t('deck', 'Unassign myself') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit"
				icon="icon-checkmark"
				:close-after-click="true"
				:disabled="isInDoneColumn && !!card.done"
				@click="changeCardDoneStatus()">
				{{ card.done ? t('deck', 'Mark as not done') : t('deck', 'Mark as done') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit"
				icon="icon-external"
				:close-after-click="true"
				@click="openCardMoveDialog">
				{{ t('deck', 'Move/copy card') }}
			</NcActionButton>
			<NcActionButton v-for="action in cardActions"
				:key="action.label"
				:close-after-click="true"
				:icon="action.icon"
				@click="action.callback(cardRichObject)">
				{{ action.label }}
			</NcActionButton>
			<NcActionButton v-if="canEditBoard" :close-after-click="true" @click="archiveUnarchiveCard()">
				<template #icon>
					<ArchiveIcon :size="20" decorative />
				</template>
				{{ card.archived ? t('deck', 'Unarchive card') : t('deck', 'Archive card') }}
			</NcActionButton>
			<NcActionButton v-if="canEdit"
				icon="icon-delete"
				:close-after-click="true"
				@click="deleteCard()">
				{{ t('deck', 'Delete card') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>
<script>
import { NcActions, NcActionButton, NcButton } from '@nextcloud/vue'
import ArchiveIcon from 'vue-material-design-icons/ArchiveOutline.vue'
import CardBulletedIcon from 'vue-material-design-icons/CardBulletedOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import cardMenu from '../../mixins/cardMenu.js'

export default {
	name: 'CardMenu',
	components: { NcActions, NcActionButton, NcButton, ArchiveIcon, CardBulletedIcon, LinkIcon, PencilIcon },
	mixins: [cardMenu],
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	emits: ['edit-title', 'open-card'],
	methods: {
		openLink() {
			window.open(this.card?.referenceData?.openGraphObject?.link)
			return false
		},
	},
}
</script>
