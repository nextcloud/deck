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
	<div class="badges">
		<i v-if="true" class="icon icon-description" title="" />
		<span v-if="true" class="due">
			<i class="icon icon-badge" />
			<span data-timestamp="" class="live-relative-timestamp"></span>
		</span>
		<div v-if="true" class="card-tasks">
			<i class="icon icon-checkmark" />
			<span>0/0</span>
		</div>
		<div v-if="true" class="card-files">
			<i class="icon icon-files-dark" />
			<span>1</span>
		</div>
		<div v-if="true" class="card-comments">
			<i class="icon icon-comment" />
			<span>1</span>
		</div>
		<div v-if="true" class="card-assigned-users">
			<div class="assigned-user">
				<avatar user="admin" />
			</div>
		</div>
	</div>
</template>
<script>
import { Avatar } from 'nextcloud-vue'

export default {
	name: 'CardBadges',
	components: { Avatar },
	props: {
		id: {}
	},
	computed: {
		compactMode() {
			return false
		}
	}
}
</script>

<style lang="scss" scoped>
	.badges {
		display: flex;
		flex-grow: 1;
	}
</style>
