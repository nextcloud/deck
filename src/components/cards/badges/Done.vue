<!--
  - @copyright Copyright (c) 2022 Thanos Kamber <thanos.kamber@gmail.com>
  -
  - @author Thanos Kamber <thanos.kamber@gmail.com>
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
	<div v-if="card" class="done">
		<transition name="zoom">
			<div class="icon-check-circle">
				<CheckCircle :size="20" :title="formatReadableDate(card.done)" />
			</div>
		</transition>
	</div>
</template>

<script>
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import readableDate from '../../../mixins/readableDate.js'

export default {
	name: 'Done',
	components: {
		CheckCircle,
	},
	mixins: [
		readableDate,
	],
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
}
</script>

<style lang="scss" scoped>
	.icon-check-circle {
		color: var(--color-success);
		margin: 14px;
	}

	@media print {
		.icon-check-circle {
			span {
				display: none;
			}

			&::before {
				color: var(--color-text-lighter);
				content: 'Done';
			}
		}
	}
</style>
