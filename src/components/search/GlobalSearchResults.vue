<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section v-if="searchQuery!==''" class="global-search">
		<header class="search-header">
			<h2>
				<NcRichText :text="$route.params.id ? t('deck', 'Search for {searchQuery} in other boards') : t('deck', 'Search for {searchQuery} in all boards')"
					:arguments="queryStringArgs" />
				<span v-if="loading" class="icon-loading-small" />
			</h2>
			<NcActions>
				<NcActionButton icon="icon-close" @click="$store.commit('setSearchQuery', '')" />
			</NcActions>
		</header>
		<div class="search-wrapper">
			<template v-if="loading || filteredResults.length > 0">
				<CardItem v-for="card in filteredResults"
					:id="card.id"
					:key="card.id"
					:standalone="true" />
				<Placeholder v-if="loading" />
				<InfiniteLoading :identifier="searchQuery" @infinite="infiniteHandler">
					<div slot="spinner" />
					<div slot="no-more" />
					<div slot="no-results">
						{{ t('deck', 'No results found') }}
					</div>
				</InfiniteLoading>
			</template>
			<template v-else>
				<p>{{ t('deck', 'No results found') }}</p>
			</template>
		</div>
	</section>
</template>

<script>
import CardItem from '../cards/CardItem.vue'
import { mapState } from 'vuex'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import InfiniteLoading from 'vue-infinite-loading'
import Placeholder from './Placeholder.vue'
import { NcActions, NcActionButton, NcRichText } from '@nextcloud/vue'

const createCancelToken = () => axios.CancelToken.source()

/**
 * @param root0
 * @param root0.query
 * @param root0.cursor
 */
function search({ query, cursor }) {
	const cancelToken = createCancelToken()

	const request = async () => axios.get(generateOcsUrl('apps/deck/api/v1.0/search'), {
		cancelToken: cancelToken.token,
		params: {
			term: query,
			limit: 20,
			cursor,
		},
	})

	return {
		request,
		cancel: cancelToken.cancel,
	}
}

export default {
	name: 'GlobalSearchResults',
	components: { CardItem, InfiniteLoading, NcRichText, Placeholder, NcActions, NcActionButton },
	data() {
		return {
			results: [],
			cancel: null,
			loading: false,
			cursor: null,
		}
	},
	computed: {
		...mapState({
			searchQuery: state => state.searchQuery,
		}),
		filteredResults() {
			const sortFn = (a, b) => a.archived - b.archived || b.lastModified - a.lastModified
			if (this.$route.params.id) {
				return this.results.filter((result) => result.relatedBoard.id.toString() !== this.$route.params.id.toString()).sort(sortFn)
			}
			return [...this.results].sort(sortFn)
		},
		queryStringArgs() {
			return {
				searchQuery: this.searchQuery,
			}
		},
	},
	watch: {
		async searchQuery() {
			this.cursor = null
			this.loading = true
			try {
				await this.search()
				this.loading = false
			} catch (e) {
				if (!axios.isCancel(e)) {
					console.error('Search request failed', e)
					this.loading = false
				}
			}
		},
	},
	methods: {
		async infiniteHandler($state) {
			this.loading = true
			try {
				const data = await this.search()
				if (data.length) {
					$state.loaded()
				} else {
					$state.complete()
				}
				this.loading = false
			} catch (e) {
				if (!axios.isCancel(e)) {
					console.error('Search request failed', e)
					$state.complete()
					this.loading = false
				}
			}
		},
		async search() {
			if (this.cancel) {
				this.cancel()
			}
			const { request, cancel } = await search({ query: this.searchQuery, cursor: this.cursor })
			this.cancel = cancel
			const { data } = await request()

			if (this.cursor === null) {
				this.results = []
			}
			if (data.ocs.data.length > 0) {
				data.ocs.data.forEach((card) => {
					this.$store.dispatch('addCardData', card)
				})
				this.results = [...this.results, ...data.ocs.data]
				this.cursor = data.ocs.data[data.ocs.data.length - 1].lastModified
			}
			return data.ocs.data
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../css/variables';

.global-search {
	width: 100%;
	padding: $board-gap;
	padding-bottom: 0;
	overflow: hidden;
	min-height: 35vh;
	max-height: 50vh;
	flex-shrink: 1;
	flex-grow: 1;
	border-top: 1px solid var(--color-border);
	z-index: 1010;
	position: relative;
	display: flex;
	flex-direction: column;

	.action-item.icon-close {
		position: absolute;
		top: 10px;
		right: 10px;
	}

	.search-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
	}

	h2 {
		margin: 0;
		padding: var(--default-grid-baseline) var(--default-grid-baseline) $board-gap;
	}

	h2 > div {
		display: inline-block;

		&.icon-loading-small {
			margin-right: 20px;
		}
	}

	h2:deep(span) {
		background-color: var(--color-background-dark);
		padding: 3px;
		border-radius: var(--border-radius);
	}

	.search-wrapper {
		overflow: auto;
		height: 100%;
		position: relative;
		display: flex;
		gap: $stack-gap;

		& > .drop-upload--card {
			flex: 0 1 $card-max-width;
			min-width: $card-min-width;
		}
	}
}
</style>
