<template>
	<div class="section-details">
		<AttachmentList
			:card-id="card.id"
			:removable="true"
			@delete-attachment="deleteAttachment"
			@restore-attachment="restoreAttachment" />
	</div>
</template>

<script>
import AttachmentList from './AttachmentList'

export default {
	components: { AttachmentList },
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {

		}
	},
	methods: {
		deleteAttachment(attachment) {
			this.$store.dispatch('deleteAttachment', attachment)
		},
		restoreAttachment(attachment) {
			this.$store.dispatch('restoreAttachment', attachment)
		},
	},
}
</script>
