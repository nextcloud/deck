/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export class SessionApi {

	url(url) {
		return generateOcsUrl(`apps/deck/api/v1.0${url}`)
	}

	async createSession(boardId) {
		return (await axios.put(this.url('/session/create'), { boardId })).data.ocs.data
	}

	async syncSession(boardId, token) {
		return await axios.post(this.url('/session/sync'), { boardId, token })
	}

	async closeSession(boardId, token) {
		return await axios.post(this.url('/session/close'), { boardId, token })
	}

	async closeSessionViaBeacon(boardId, token) {
		const body = {
			boardId,
			token,
		}
		const headers = {
			type: 'application/json',
		}
		const blob = new Blob([JSON.stringify(body)], headers)
		navigator.sendBeacon(this.url('/session/close'), blob)
	}

}

export const sessionApi = new SessionApi()
