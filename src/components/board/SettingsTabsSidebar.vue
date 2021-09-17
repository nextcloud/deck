<template>
	<div>
		<label for="settings-category">{{ t('deck', 'Category') }}</label>
		<Multiselect
			id="settings-category"
			v-model="category"
			:placeholder="t('deck', 'select category')"
			:options="selectCategories"
			:taggable="true"
			:clear-on-select="false"
			@tag="onNewCategory" />
	</div>
</template>
<script>

import { mapGetters } from 'vuex'
import { Multiselect } from '@nextcloud/vue'

export default {
	name: 'SettingsTabsSidebar',
	components: {
		Multiselect,
	},
	props: {
		board: {
			type: Object,
			default: undefined,
		},
	},
	computed: {
		...mapGetters(['categories']),
		selectCategories() {
			return [this.t('deck', 'uncategorised')].concat(this.categories)
		},
		category: {
			get() {
				if (!this.board.category) {
					return this.t('deck', 'uncategorised')
				}
				return this.board.category
			},
			set(category) {
				const boardCopy = JSON.parse(JSON.stringify(this.board))

				if (category === this.t('deck', 'uncategorised')) {
					boardCopy.category = null
				} else {
					boardCopy.category = category
				}

				this.$store.dispatch('updateBoard', boardCopy)
				this.$store.dispatch('setCurrentBoard', boardCopy)
			},
		},
	},
	methods: {
		onNewCategory(category) {
			this.category = category
		},
	},
}

</script>
