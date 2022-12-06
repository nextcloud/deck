<!--
	- @copyright Copyright (c) 2022 Adrian Missy <adrian.missy@onewavestudios.com>
	-
	- @author Adrian Missy <adrian.missy@onewavestudios.com>
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
	<div v-if="card" class="cardid">
		<span>#{{ card.id }}</span>
	</div>
</template>

<script>

export default {
	name: 'CardId',
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
}
</script>

<style lang="scss" scoped>
	.cardid {
		font-size: .9em;
	}
</style>
