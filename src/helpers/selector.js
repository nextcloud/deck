/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as Vue from 'vue'
import { appendMountTarget, mountComponent } from '../lib/mountComponent.js'

const buildSelector = (selector, options = {}) => {
	const {
		props = {},
		resolveEvent = 'select',
		rejectEvents = ['close'],
		rejectMessage = 'Selection canceled',
	} = options

	return new Promise((resolve, reject) => {
		const container = appendMountTarget()
		let mountedComponent = null
		const cleanup = () => {
			mountedComponent?.destroy({ removeElement: true })
		}

		const on = {
			[resolveEvent]: (value) => {
				cleanup()
				resolve(value)
			},
		}

		rejectEvents.forEach((eventName) => {
			on[eventName] = () => {
				cleanup()
				reject(new Error(rejectMessage))
			}
		})

		mountedComponent = mountComponent(Vue, selector, {
			target: container,
			props,
			on,
		})
	})
}

export {
	buildSelector,
}
