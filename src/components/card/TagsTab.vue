<template>
	<div class="section-details">
		<div v-if="showSelelectTags || card.labels.length <= 0" @mouseleave="showSelelectTags = false">
			<Multiselect v-model="assignedLabels"
				:multiple="true"
				:disabled="!canEdit"
				:options="labelsSorted"
				:placeholder="t('deck', 'Assign a tag to this card…')"
				:taggable="true"
				label="title"
				track-by="id"
				@select="addLabelToCard"
				@remove="removeLabelFromCard">
				<template #option="scope">
					<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">
						{{ scope.option.title }}
					</div>
				</template>
				<template #tag="scope">
					<div :style="{ backgroundColor: '#' + scope.option.color, color: textColor(scope.option.color)}" class="tag">
						{{ scope.option.title }}
					</div>
				</template>
			</Multiselect>
		</div>
		<div v-else-if="card.labels.length > 0" class="labels">
			<div v-for="label in card.labels"
				:key="label.id"
				:style="labelStyle(label)"
				class="labels-item">
				<span @click.stop="applyLabelFilter(label)">{{ label.title }}</span>
			</div>
			<div class="button new select-tag" @click="add">
				<span class="icon icon-add" />
				<span class="hidden-visually" />
			</div>
		</div>
	</div>
</template>
<script>
import { Multiselect } from '@nextcloud/vue'
import { mapState, mapGetters } from 'vuex'
import Color from '../../mixins/color'
import labelStyle from '../../mixins/labelStyle'

export default {
	components: { Multiselect },
	mixins: [Color, labelStyle],
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			assignedLabels: null,
			showSelelectTags: false,
			copiedCard: null,
		}
	},
	computed: {
		...mapState({
			currentBoard: state => state.currentBoard,
		}),
		...mapGetters(['canEdit']),
		labelsSorted() {
			return [...this.currentBoard.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
	},
	mounted() {
		this.initialize()
	},
	methods: {
		add() {
			this.showSelelectTags = true
			this.$emit('active-tab', 'tags')
		},
		async initialize() {
			if (!this.card) {
				return
			}

			this.copiedCard = JSON.parse(JSON.stringify(this.card))
			this.assignedLabels = [...this.card.labels].sort((a, b) => (a.title < b.title) ? -1 : 1)
		},
		openCard() {
			const boardId = this.card && this.card.boardId ? this.card.boardId : this.$route.params.id
			this.$router.push({ name: 'card', params: { id: boardId, cardId: this.card.id } }).catch(() => {})
		},
		addLabelToCard(newLabel) {
			this.copiedCard.labels.push(newLabel)
			const data = {
				card: this.copiedCard,
				labelId: newLabel.id,
			}
			this.$store.dispatch('addLabel', data)
		},
		removeLabelFromCard(removedLabel) {

			const removeIndex = this.copiedCard.labels.findIndex((label) => {
				return label.id === removedLabel.id
			})
			if (removeIndex !== -1) {
				this.copiedCard.labels.splice(removeIndex, 1)
			}

			const data = {
				card: this.copiedCard,
				labelId: removedLabel.id,
			}
			this.$store.dispatch('removeLabel', data)
		},
	},
}
</script>

<style lang="scss" scoped>
.labels {
	display: flex;
	justify-content: flex-start;
	align-items: center;

	&-item {
		border-radius: 5px;
		margin-right: 5px;
		min-width: 110px;
		height: 32px;
		display: flex;
		justify-content: center;
		align-items: center;
	}
}

.select-tag {
	height: 32px;
	width: 32px;
	padding: 5px 7px;
}
</style>
