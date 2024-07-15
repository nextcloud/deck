<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="activity" class="activity">
		<div class="activity--header">
			<img :src="activity.icon" class="activity--icon">
			<NcRichText class="activity--subject" :text="message.subject" :arguments="message.parameters" />
			<div class="activity--timestamp" :name="formatReadableDate(activity.datetime)">
				{{ relativeDate(activity.datetime) }}
			</div>
		</div>
		<!-- FIXME ins/del tags do no longer work with activity so we should get rid of that -->
		<p v-if="activity.message" class="activity--message" v-html="sanitizedMessage" />
	</div>
</template>

<script>
import { NcRichText, NcUserBubble } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import DOMPurify from 'dompurify'
import relativeDate from '../mixins/relativeDate.js'
import formatReadableDate from '../mixins/readableDate.js'

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
		NcRichText,
	},
	mixins: [relativeDate, formatReadableDate],
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
			margin-left: var(--default-clickable-area);
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
