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
		<div class="activity--header">
			<img :src="activity.icon" class="activity--icon">
			<RichText class="activity--subject" :text="message.subject" :arguments="message.parameters" />
			<div class="activity--timestamp">
				{{ relativeDate(activity.datetime) }}
			</div>
		</div>
		<!-- FIXME ins/del tags do no longer work with activity so we should get rid of that -->
		<p v-if="activity.message" class="activity--message" v-html="sanitizedMessage" />
	</div>
</template>

<script>
import RichText from '@juliushaertl/vue-richtext'
import { NcUserBubble } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import DOMPurify from 'dompurify'
import relativeDate from '../mixins/relativeDate'

const InternalLink = {
	name: 'InternalLink',
	functional: true,
	props: {
		href: {
			type: String,
			default: '',
		},
		name: {
			type: String,
			default: '',
		},
	},
	render(createElement, context) {
		return createElement('a', { attrs: { href: context.props.href }, style: { 'font-weight': 600 } }, context.props.name)
	},
}
export default {
	name: 'ActivityEntry',
	components: {
		RichText,
	},
	mixins: [relativeDate],
	props: {
		activity: {
			type: Object,
			default: null,
		},
	},
	computed: {
		message() {
			const subject = this.activity.subject_rich[0]
			const parameters = JSON.parse(JSON.stringify(this.activity.subject_rich[1]))
			if (parameters.after && typeof parameters.after.id === 'string' && parameters.after.id.startsWith('dt:')) {
				const dateTime = parameters.after.id.slice(3)
				parameters.after.name = moment(dateTime).format('L LTS')
			}

			Object.keys(parameters).forEach(function(key, index) {
				const { type } = parameters[key]
				switch (type) {
				case 'highlight':
					parameters[key] = {
						component: InternalLink,
						props: {
							href: parameters[key].link,
							name: parameters[key].name,
						},
					}
					break
				case 'user':
					parameters[key] = {
						component: NcUserBubble,
						props: {
							user: parameters[key].id,
							displayName: parameters[key].name,
						},
					}
					break
				default:
					parameters[key] = `{${key}}`
				}

			})

			return {
				subject, parameters,
			}
		},

		sanitizedMessage() {
			return DOMPurify.sanitize(this.activity.message, { ALLOWED_TAGS: ['ins', 'del'], ALLOWED_ATTR: ['class'] })
		},

	},
}
</script>

<style scoped lang="scss">
	.activity {

		.activity--header {
			display: flex;
			padding: 10px;
		}

		.activity--icon {
			width: 16px;
			height: 16px;
			flex-shrink: 0;
			flex-grow: 0;
		}
		.activity--subject {
			margin-left: 10px;
		}
		.activity--message {
			margin-left: 44px;
			color: var(--color-text-light);
			margin-bottom: 10px;
		}
		.activity--timestamp {
			flex-grow: 1;
			color: var(--color-text-maxcontrast);
			text-align: right;
			font-size: 0.8em;
			padding: 1px;
		}
	}
</style>
<style>
	.visualdiff ins {
		color: green;
	}

	.visualdiff del {
		color: darkred;
	}
</style>
