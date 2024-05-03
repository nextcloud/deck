/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const shareUrl = generateOcsUrl('apps/files_sharing/api/v1/shares')

const createShare = async function({ path, permissions, shareType, shareWith, publicUpload, password, sendPasswordByTalk, expireDate, label }) {
	try {
		const request = await axios.post(shareUrl, { path, permissions, shareType, shareWith, publicUpload, password, sendPasswordByTalk, expireDate, label })
		if (!request?.data?.ocs) {
			throw request
		}
		return request
	} catch (error) {
		console.error('Error while creating share', error)
		OC.Notification.showTemporary(t('files_sharing', 'Error creating the share'), { type: 'error' })
		throw error
	}
}

export {
	createShare,
}
