/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const shareUrl = generateOcsUrl('apps/files_sharing/api/v1/shares')

/**
 *
 * @param root0
 * @param root0.path
 * @param root0.permissions
 * @param root0.shareType
 * @param root0.shareWith
 * @param root0.publicUpload
 * @param root0.password
 * @param root0.sendPasswordByTalk
 * @param root0.expireDate
 * @param root0.label
 */
async function createShare({ path, permissions, shareType, shareWith, publicUpload, password, sendPasswordByTalk, expireDate, label }) {
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
