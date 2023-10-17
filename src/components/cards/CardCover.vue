<!--
  - @copyright Copyright (c) 2023 Johannes Szeibert <johannes@szeibert.de>
  -
  - @author Johannes Szeibert <johannes@szeibert.de>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-if="cardId && ( attachments.length > 0 )" class="card-cover">
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
	height: 100px;
	display: flex;
	.image-wrapper {
		flex: 1;
		position: relative;
		background-size: cover;
		background-repeat: no-repeat;
		background-position: center center;
		&.rounded-left {
			border-top-left-radius: 10px;
		}
		&.rounded-right {
			border-top-right-radius: 10px;
		}
	}
}
</style>
