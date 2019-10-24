<template>
	<form @submit.prevent="$emit('update', commentMsg)">
		<input v-model="commentMsg" type="text" autofocus
			required>
		<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit"
			value="">
		<input type="submit" value="" class="icon-close"
			@click.stop.prevent="$emit('close')">
	</form>
</template>

<script>

export default {
	name: 'CommentItemEdit',
	props: {
		comment: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			edit: false,
			commentMsg: ''
		}
	},
	created() {
		this.commentMsg = this.comment.message
	}
}
</script>

<style lang="scss">

</style>
