<!--
  - @copyright Copyright (c) 2022 Julius Härtl <rija.propitech@gmail.com>
  -
  - @author Julius Härtl <rija.propitech@gmail.com>
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
	<div class="progress-main">
		<a class="fileicon" />
		<div class="details">
			<a>
				<div class="filename">
					<span class="basename">{{ attachment.name }}</span>
				</div>
				<progress :value="attachment.progress" max="100" />
			</a>
		</div>
	</div>
</template>

<script>

export default {
	name: 'AttachmentUploadProgress',
	props: {
		attachment: {
			type: Object,
			default: null,
		},
	},
}

</script>

<style lang="scss" scoped>
	.progress-main {
		width: 100%;
	}
</style>
