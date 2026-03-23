<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="infinite-loader">
		<div v-if="isLoading" class="infinite-loader__spinner">
			<slot name="spinner" />
		</div>
		<div v-else-if="isComplete && !hasLoadedItems" class="infinite-loader__no-results">
			<slot name="no-results" />
		</div>
		<div v-else-if="isComplete" class="infinite-loader__no-more">
			<slot name="no-more" />
		</div>
		<div v-show="!isComplete" ref="sentinel" class="infinite-loader__sentinel" />
	</div>
</template>

<script>
export default {
	name: 'InfiniteLoader',
	props: {
		identifier: {
			type: [Number, String],
			default: null,
		},
	},
	data() {
		return {
			observer: null,
			isLoading: false,
			isComplete: false,
			hasLoadedItems: false,
		}
	},
	watch: {
		identifier() {
			this.reset()
			this.$emit('change')
			this.$nextTick(() => {
				this.requestLoad()
			})
		},
	},
	mounted() {
		this.observer = new IntersectionObserver((entries) => {
			if (!entries.some((entry) => entry.isIntersecting)) {
				return
			}

			this.requestLoad()
		})

		this.observer.observe(this.$refs.sentinel)
		this.requestLoad()
	},
	beforeUnmount() {
		this.disconnectObserver()
	},
	methods: {
		disconnectObserver() {
			this.observer?.disconnect()
		},
		reset() {
			this.isLoading = false
			this.isComplete = false
			this.hasLoadedItems = false
		},
		requestLoad() {
			if (this.isLoading || this.isComplete) {
				return
			}

			this.isLoading = true
			this.$emit('infinite', {
				loaded: this.markLoaded,
				complete: this.markComplete,
			})
		},
		markLoaded() {
			this.hasLoadedItems = true
			this.isLoading = false
			this.$nextTick(() => {
				this.requestLoad()
			})
		},
		markComplete() {
			this.isLoading = false
			this.isComplete = true
		},
	},
}
</script>

<style scoped>
	.infinite-loader {
		width: 100%;
	}

	.infinite-loader__sentinel {
		width: 100%;
		height: 1px;
	}
</style>
