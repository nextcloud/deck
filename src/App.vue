<!--
  - SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script>
import { NcAppContent, NcContent, NcModal } from '@nextcloud/vue'
import CardMoveDialog from './CardMoveDialog.vue'
import AppNavigation from './components/navigation/AppNavigation.vue'
import KeyboardShortcuts from './components/KeyboardShortcuts.vue'
export default {
	name: 'App',
	components: {
		NcContent,
		AppNavigation,
		NcAppContent,
		KeyboardShortcuts,
		CardMoveDialog,
		NcModal,
	},
	computed: {
		cardDetailsInModal() {
			return this.$store.getters.config('cardDetailsInModal')
		},
	},
	methods: {
		hideModal() {
			this.$router.push({ name: 'board' })
		},
	},
}
</script>

<template>
	<NcContent app-name="deck">
		<AppNavigation />
		<NcAppContent :allow-swipe-navigation="false">
			<router-view />
		</NcAppContent>
		<div v-if="$route.params.id || $route.params.cardId">
			<NcModal v-if="cardDetailsInModal && $route.params.cardId"
				:name="t('deck', 'Card details')"
				:clear-view-delay="0"
				:close-button-contained="true"
				size="large"
				@close="hideModal()">
				<div class="modal__content modal__card">
					<router-view name="sidebar" />
				</div>
			</NcModal>
			<router-view name="sidebar" :visible="!cardDetailsInModal || !$route.params.cardId" />
		</div>

		<KeyboardShortcuts />
		<CardMoveDialog />
	</NcContent>
</template>
