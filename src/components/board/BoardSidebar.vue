<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="sidebar">
		<div class="sidebar-header">
			<h3>{{ board.title }}</h3>
		</div>

		<ul class="tab-headers">
			<li v-for="tab in tabs" :class="{ 'selected': tab.isSelected }" :key="tab.name">
				<a @click="setSelectedHeader(tab.name)">{{ tab.name }}</a>
			</li>
		</ul>

		<div class="tabsContainer">
			<div class="tab">
				<ul
					v-if="activeTab === 'Sharing'"
					id="shareWithList"
					class="shareWithList"
				>
					<li>
						<avatar :user="board.owner.uid" />
						<span class="has-tooltip username">
							{{ board.owner.displayname }}
						</span>
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import { Avatar } from 'nextcloud-vue'
import { mapState } from 'vuex'

export default {
	name: 'BoardSidebar',
	components: { Avatar },
	props: {
	},
	data() {
		return {
			activeTab: 'Sharing',
			tabs: [
				{
					name: 'Sharing',
					isSelected: true
				},
				{
					name: 'Tags',
					isSelected: false
				},
				{
					name: 'Deleted items',
					isSelected: false
				},
				{
					name: 'Timeline',
					isSelected: false
				}
			]
		}
	},
	computed: {
		...mapState({
			board: state => state.currentBoard
		})
	},
	methods: {
		closeSidebar() {
			this.$store.dispatch('toggleSidebar')
		},
		setSelectedHeader(tabName) {
			this.activeTab = tabName
			this.tabs.forEach(tab => {
				tab.isSelected = (tab.name === tabName)
			})
		}
	}
}
</script>

<style lang="scss" scoped>
  .sidebar-header {
    h3 {
      font-size: 14pt;
      padding: 15px 15px 3px;
      margin: 0;
      overflow: hidden;
    }
  }
  .icon-close {
    position: absolute;
    top: 0px;
    right: 0px;
    padding: 14px;
    height: 24px;
    width: 24px;
  }
  ul.tab-headers {
	margin: 15px 15px 0 15px;
		li {
			display: inline-block;
			&.selected {
				color: #000;
				border-bottom: 1px solid #4d4d4d;
				font-weight: 600;
			}
			a {
				padding: 12px;
			}
		}
  }
	.tabsContainer {
		.tab {
			padding: 0 15px 15px;
		}
	}
</style>
