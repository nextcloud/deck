<!--
  - @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-if="searchQuery!==''" class="global-search">
		<h2>
			<RichText :text="t('deck', 'Search for {searchQuery} in all boards')" :arguments="queryStringArgs" />
			<div v-if="loading" class="icon-loading-small" />
		</h2>
		<NcActions>
			<NcActionButton icon="icon-close" @click="$store.commit('setSearchQuery', '')" />
		</NcActions>
		<div class="search-wrapper">
			<div v-if="loading || filteredResults.length > 0" class="search-results">
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
			</div>
			<div v-else>
				<p>{{ t('deck', 'No results found') }}</p>
			</div>
		</div>
	</div>
</template>

<script>
import CardItem from '../cards/CardItem'
import { mapState } from 'vuex'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import InfiniteLoading from 'vue-infinite-loading'
import RichText from '@juliushaertl/vue-richtext'
import Placeholder from './Placeholder'
import { NcActions, NcActionButton } from '@nextcloud/vue'

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
	components: { CardItem, InfiniteLoading, RichText, Placeholder, NcActions, NcActionButton },
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
	padding: $board-spacing + $stack-spacing;
	padding-bottom: 0;
	overflow: hidden;
	min-height: 35vh;
	max-height: 50vh;
	flex-shrink: 1;
	flex-grow: 1;
	border-top: 1px solid var(--color-border);
	z-index: 1010;
	position: relative;

	.action-item.icon-close {
		position: absolute;
		top: 10px;
		right: 10px;
	}
	.search-wrapper {
		overflow: scroll;
		height: 100%;
		position: relative;
		padding: 10px;
	}

	h2 > div {
		display: inline-block;

		&.icon-loading-small {
			margin-right: 20px;
		}
	}
	h2::v-deep span {
		background-color: var(--color-background-dark);
		padding: 3px;
		border-radius: var(--border-radius);
	}

	.search-results {
		display: flex;
		flex-wrap: wrap;

		& > div {
			flex-grow: 0;
		}
	}
	&::v-deep .card {
		width: $stack-width;
		margin-right: $stack-spacing;
	}
}
</style>
