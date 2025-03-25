<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigationItem v-if="!editing"
		:name="t('deck', 'Add board')"
		icon="icon-add"
		@click.prevent.stop="startCreateBoard" />
	<div v-else class="board-create">
		<NcColorPicker v-model="color" class="app-navigation-entry-bullet-wrapper" :disabled="loading">
			<div :style="{ backgroundColor: color }" class="color0 icon-colorpicker app-navigation-entry-bullet" />
		</NcColorPicker>
		<form @submit.prevent.stop="createBoard">
			<NcTextField ref="inputField"
				v-model="value"
				:disable="loading"
				:placeholder="t('deck', 'Board name')"
				type="text"
				required />
			<NcButton type="tertiary"
				:disabled="loading"
				:title="t('deck', 'Cancel edit')"
				@click.stop.prevent="cancelEdit">
				<template #icon>
					<CloseIcon :size="20" />
				</template>
			</NcButton>
			<NcButton type="tertiary"
				native-type="submit"
				:disabled="loading"
				:title="t('deck', 'Save board')">
				<template #icon>
					<CheckIcon v-if="!loading" :size="20" />
					<NcLoadingIcon v-else :size="20" />
				</template>
			</NcButton>
		</form>
	</div>
</template>

<script>
import { NcButton, NcColorPicker, NcAppNavigationItem, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

/**
 *
 */
function randomColor() {
	let randomHexColor = ((1 << 24) * Math.random() | 0).toString(16)
	while (randomHexColor.length < 6) {
		randomHexColor = '0' + randomHexColor
	}
	return '#' + randomHexColor
}

export default {
	name: 'AppNavigationAddBoard',
	components: { NcButton, NcColorPicker, NcAppNavigationItem, NcLoadingIcon, NcTextField, CheckIcon, CloseIcon },
	directives: {},
	props: {},
	data() {
		return {
			value: '',
			classes: [],
			editing: false,
			loading: false,
			color: randomColor(),
		}
	},
	methods: {
		startCreateBoard(e) {
			this.editing = true
			this.$nextTick(() => {
				this.$refs?.inputField.focus()
			})
		},
		async createBoard(e) {
			this.loading = true
			const title = this.value.trim()
			await this.$store.dispatch('createBoard', {
				title,
				color: this.color.substring(1),
			})
			this.loading = false
			this.editing = false
			this.color = randomColor()
		},
		cancelEdit(e) {
			this.editing = false
			this.color = randomColor()
		},
	},
}
</script>
<style lang="scss" scoped>
	.board-create {
		order: 1;
		display: flex;
		height: var(--default-clickable-area);

		form {
			display: flex;
			flex-grow: 1;

			input[type='text'] {
				flex-grow: 1;
			}
		}
	}

	.app-navigation-entry-bullet-wrapper {
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		.color0 {
			width: 24px !important;
			margin: var(--default-grid-baseline);
			height: 24px;
			border-radius: 50%;
			background-size: 14px;
		}
	}
</style>
