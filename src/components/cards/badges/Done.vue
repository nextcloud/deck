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
			<div v-if="card.done" class="icon-checkmark done-text icon">
				<span>{{ t('deck', 'Done') }}</span>
			</div>
		</transition>
	</div>
</template>

<script>

export default {
	name: 'Done',
	props: {
		card: {
			type: Object,
			default: null,
		},
	},
}
</script>

<style lang="scss" scoped>
	.icon.done-text {
		background-position: 4px center;
		background-color: var(--color-success);
		border-radius: 3px;
		margin: 9px 0;
		margin-right: 9px;
		padding: 3px 4px;
		font-size: 90%;
		display: flex;
		align-items: center;
		flex-shrink: 1;
		z-index: 2;
		color: var(--color-primary-text);

		.icon {
			background-size: contain;
		}

		&::before,
		span {
			margin-left: 20px;
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
		}
	}

	@media print {
		.icon.due {
			background-color: transparent !important;

			span {
				display: none;
			}

			&::before {
				content: "Done";
			}
		}
	}
</style>
