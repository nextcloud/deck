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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<NcModal class="card-selector" @close="close">
		<CreateNewCardCustomPicker :title="title"
			:description="description"
			show-created-notice
			@cancel="close"
			@close="close" />
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import CreateNewCardCustomPicker from './views/CreateNewCardCustomPicker.vue'

export default {
	name: 'CardCreateDialog',
	components: {
		NcModal,
		CreateNewCardCustomPicker,
	},
	props: {
		title: {
			type: String,
			default: '',
		},
		description: {
			type: String,
			default: '',
		},
	},
}
</script>
