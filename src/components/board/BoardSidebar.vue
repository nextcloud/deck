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
  <div class="sidebar-header">
    <a class="icon-close" title="Close" @click="closeSidebar">
      <span class="hidden-visually">Close</span>
	  </a>
    <h3>{{ board.title }}</h3>
  </div>
</template>

<script>
export default {
	name: 'BoardSidebar',
	props: {
		board: {
			type: Object,
			default: function() {
				return {}
			}
		}
  },
  methods: {
		closeSidebar: function() {
			this.$store.dispatch('toggleSidebar')
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
</style>
