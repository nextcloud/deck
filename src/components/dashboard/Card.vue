<template>
	<a :key="card.id"
		:href="cardLink"
		target="_blank"
		class="card">
		<div class="card--header">
			<DueDate class="right" :card="card" />
			<span class="title" dir="auto">{{ card.title }}</span>
		</div>
		<ul v-if="card.labels && card.labels.length"
			class="labels">
			<li v-for="label in card.labels" :key="label.id" :style="labelStyle(label)">
				<span dir="auto">{{ label.title }}</span>
			</li>
		</ul>
	</a>
</template>

<script>
import DueDate from '../cards/badges/DueDate.vue'
import { generateUrl } from '@nextcloud/router'
import labelStyle from '../../mixins/labelStyle.js'

export default {
	name: 'Card',
	components: { DueDate },
	mixins: [labelStyle],
	props: {
		card: {
		  type: Object,
			required: true,
		},
	},
	computed: {
		cardLink() {
			return generateUrl('/apps/deck') + `#/board/${this.card.boardId}/card/${this.card.id}`
		},
	},
}
</script>

<style  lang="scss" scoped>
	@import '../../css/labels';

	.card {
		display: block;
		border-radius: var(--border-radius-large);
		padding: 8px;
		height: 60px;

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
		}
	}

	.labels {
		margin-left: 0;
	}

	.duedate:deep(.due) {
		margin: 0 0 0 10px;
		padding: 2px 4px;
		font-size: 90%;
	}

	.right {
		float: right;
	}
</style>
