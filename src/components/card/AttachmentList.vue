<!--
  - @copyright Copyright (c) 2020 Jakob Röhrl <jakob.roehrl@web.de>
  -
  - @author Jakob Röhrl <jakob.roehrl@web.de>
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
	<div class="attachment-list">
		<ul>
			<li v-for="attachment in attachments"
				:key="attachment.id"
				class="attachment">
				<a class="fileicon" :style="mimetypeForAttachment(attachment.extendedData.mimetype)" :href="attachmentUrl(attachment)" />
				<div class="details">
					<a :href="attachmentUrl(attachment)" target="_blank">
						<div class="filename">
							<span class="basename">{{ attachment.data }}</span>
						</div>
						<span class="filesize">{{ formattedFileSize(attachment.extendedData.filesize) }}</span>
						<span class="filedate">{{ relativeDate(attachment.createdAt*1000) }}</span>
						<span class="filedate">{{ attachment.createdBy }}</span>
					</a>
				</div>
				<Actions v-if="selectable">
					<ActionButton icon="icon-confirm" @click="$emit('selectAttachment', attachment)">
						{{ t('deck', 'Add this attachment') }}
					</ActionButton>
				</Actions>
				<Actions v-if="removable">
					<ActionButton v-if="attachment.deletedAt === 0" icon="icon-delete" @click="$emit('deleteAttachment', attachment)">
						{{ t('deck', 'Delete Attachment') }}
					</ActionButton>

					<ActionButton v-else icon="icon-history" @click="$emit('restoreAttachment', attachment)">
						{{ t('deck', 'Restore Attachment') }}
					</ActionButton>
				</Actions>
			</li>
		</ul>
	</div>
</template>

<script>
import { Actions, ActionButton } from '@nextcloud/vue'
import relativeDate from '../../mixins/relativeDate'
import { formatFileSize } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'AttachmentList',
	components: {
		Actions,
		ActionButton,
	},
	mixins: [relativeDate],
	props: {
		cardId: {
			type: Number,
			required: true,
		},
		selectable: {
			type: Boolean,
			required: false,
		},
		removable: {
			type: Boolean,
			required: false,
		},
	},
	data() {
		return {

		}
	},
	computed: {
		attachments() {
			return [...this.$store.getters.attachmentsByCard(this.cardId)].sort((a, b) => b.id - a.id)
		},
		mimetypeForAttachment() {
			return (mimetype) => {
				const url = OC.MimeType.getIconUrl(mimetype)
				const styles = {
					'background-image': `url("${url}")`,
				}
				return styles
			}
		},
		attachmentUrl() {
			return (attachment) => generateUrl(`/apps/deck/cards/${attachment.cardId}/attachment/${attachment.id}`)
		},
		formattedFileSize() {
			return (filesize) => formatFileSize(filesize)
		},
	},
	watch: {

	},
}
</script>

<style lang="scss" scoped>
	.attachment-list {
		&.selector {
			padding: 10px;
			position: absolute;
			width: 30%;
			max-width: 500px;
			min-width: 200px;
			max-height: 50%;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background-color: #eee;
			z-index: 2;
			border-radius: 3px;
			box-shadow: 0 0 3px darkgray;
			overflow: scroll;
		}
		h3.attachment-selector {
			margin: 0 0 10px;
			padding: 0;
			.icon-close {
				display: inline-block;
				float: right;
			}
		}

		li.attachment {
			display: flex;
			padding: 3px;
			min-height: 44px;

			&.deleted {
				opacity: .5;
			}

			.fileicon {
				display: inline-block;
				min-width: 32px;
				width: 32px;
				height: 32px;
				background-size: contain;
			}
			.details {
				flex-grow: 1;
				flex-shrink: 1;
				min-width: 0;
				flex-basis: 50%;
				line-height: 110%;
				padding: 2px;
			}
			.filename {
				width: 70%;
				display: flex;
				.basename {
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
					padding-bottom: 2px;
				}
				.extension {
					opacity: 0.7;
				}
			}
			.filesize, .filedate {
				font-size: 90%;
				color: darkgray;
			}
			.app-popover-menu-utils {
				position: relative;
				right: -10px;
				button {
					height: 32px;
					width: 42px;
				}
			}
			button.icon-history {
				width: 44px;
			}
			progress {
				margin-top: 3px;
			}
		}
	}

</style>
