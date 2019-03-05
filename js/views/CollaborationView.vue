<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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
	<div>
		<collection-list v-if="boardId" type="deck" :id="boardId" :name="boardTitle"></collection-list>
	</div>
</template>

<script>
	import { CollectionList } from 'nextcloud-vue-collections';
	import Vue from 'vue';
	import PopoverMenu from 'nextcloud-vue/dist/Components/PopoverMenu'
	import vClickOutside from 'v-click-outside'

	Vue.component('popover-menu', PopoverMenu);
	Vue.use(vClickOutside)

	export default {
		name: 'CollaborationView',
		computed: {
			boardId() {
				if (this.$root.model && this.$root.model.id) {
					return '' + this.$root.model.id;
				}
				return null;
			},
			boardTitle() {
				if (this.$root.model && this.$root.model.title) {
					return '' + this.$root.model.title;
				}
				return '';
			}
		},
		components: {
			CollectionList: CollectionList
		}
	}
</script>
