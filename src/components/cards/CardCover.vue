<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="referencePreview" class="card-cover">
		<div class="image-wrapper rounded-left rounded-right" :style="{ backgroundImage: `url(${referencePreview})`}" />
	</div>
	<div v-else-if="cardId && ( attachments.length > 0 )" class="card-cover">
		<div v-for="(attachment, index) in attachments"
			:key="attachment.id"
			:class="['image-wrapper', { 'rounded-left': index === 0 }, { 'rounded-right': index === attachments.length - 1 }]"
			:style="{ backgroundImage: `url(${attachmentPreview(attachment)})` }" />
	</div>
</template>
<script>
import { mapActions } from 'vuex'
import { generateUrl } from '@nextcloud/router'
export default {
	name: 'CardCover',
	props: {
		cardId: {
			type: Number,
			required: true,
		},
	},
	computed: {
		attachments() {
			return [...this.$store.getters.attachmentsByCard(this.cardId)]
				// Filter deleted and hasPreview
				.filter(attachment => attachment.deletedAt >= 0 && attachment.extendedData.hasPreview)
				// sort by id (same as in AttachmentList) to get Newest
				.sort((a, b) => b.id - a.id)
				// limit to 3 like with android Deck app
				.slice(0, 3)
		},
		attachmentPreview() {
			// FIXME find a better way to get the stack-width
			const stackWidth = getComputedStyle(document.documentElement).getPropertyValue('--stack-width').trim()
			const x = Math.ceil(parseInt(stackWidth) / this.attachments.length) | 260
			const y = 100
			return attachment => (
				// The core preview provider is a bit strange at times, providing much larger than needed images
				// when cropping is enabled. Therefore use a=1 to not crop the image and let css handle the overflow
				attachment.extendedData.fileid ? generateUrl(`/core/preview?fileId=${attachment.extendedData.fileid}&x=${x}&y=${y}&a=1`) : null
			)
		},
		card() {
			return this.$store.getters.cardById(this.cardId)
		},
		referencePreview() {
			return this.card?.referenceData?.richObject?.thumb
		},
	},
	watch: {
		cardId: {
			immediate: true,
			handler() {
				if (this.$store.getters.cardById(this.cardId)?.attachmentCount > 0) {
					this.fetchAttachments(this.cardId)
				}
			},
		},
	},
	methods: {
		...mapActions([
			'fetchAttachments',
		]),
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/variables';

.card-cover {
	height: 90px;
	display: flex;
	margin-top: -4px;
	margin-left: -4px;
	margin-right: -4px;

	.image-wrapper {
		flex: 1;
		position: relative;
		background-size: cover;
		background-repeat: no-repeat;
		background-position: center center;
		&.rounded-left {
			border-top-left-radius: calc(var(--border-radius-large) - 1px);
		}
		&.rounded-right {
			border-top-right-radius: calc(var(--border-radius-large) - 1px);
		}
	}
}
</style>
