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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-if="activity" class="activity">
		<img :src="activity.icon" class="activity--icon">
		<div class="activity--message" v-html="parseMessage(activity)" />
		<div class="activity--timestamp">
			{{ getTime(activity.datetime) }}
		</div>
	</div>
</template>

<script>
export default {
	name: 'ActivityEntry',
	props: {
		activity: {
			type: Object,
			default: null,
		},
	},
	methods: {
		getTime(timestamp) {
			return OC.Util.relativeModifiedDate(timestamp)
		},
		parseMessage(activity) {
			const subject = activity.subject_rich[0]
			const parameters = JSON.parse(JSON.stringify(activity.subject_rich[1]))
			if (parameters.after && typeof parameters.after.id === 'string' && parameters.after.id.startsWith('dt:')) {
				const dateTime = parameters.after.id.substr(3)
				parameters.after.name = window.moment(dateTime).format('L LTS')
			}
			return OCA.Activity.RichObjectStringParser.parseMessage(subject, parameters)
		},
	},
}
</script>

<style scoped lang="scss">
	.activity {
		display: flex;
		padding: 10px;

		.activity--icon {
			width: 16px;
			height: 16px;
			flex-shrink: 0;
			flex-grow: 0;
		}
		.activity--message {
			margin-left: 10px;
		}
		.activity--timestamp {
			color: var(--color-text-maxcontrast);
			text-align: right;
			font-size: 0.8em;
			width: 25%;
			padding: 1px;
		}
	}
</style>
