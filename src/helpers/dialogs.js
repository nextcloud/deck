/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	getFilePickerBuilder as nextcloudGetFilePickerBuilder,
	showError as nextcloudShowError,
	showLoading as nextcloudShowLoading,
	showSuccess as nextcloudShowSuccess,
	showUndo as nextcloudShowUndo,
	showWarning as nextcloudShowWarning,
} from '@nextcloud/dialogs'

import '@nextcloud/dialogs/style.css'

const normalizeErrorMessage = (error) => {
	if (typeof error === 'string') {
		return error
	}

	if (error?.response?.data?.message) {
		return error.response.data.message
	}

	if (error?.message) {
		return error.message
	}

	return ''
}

const showError = (error, options) => {
	const message = normalizeErrorMessage(error)
	return nextcloudShowError(message, options)
}

const showLoading = (message, options) => {
	return nextcloudShowLoading(message, options)
}

const showSuccess = (message, options) => {
	return nextcloudShowSuccess(message, options)
}

const showUndo = (message, callback, options) => {
	return nextcloudShowUndo(message, callback, options)
}

const showWarning = (message, options) => {
	return nextcloudShowWarning(message, options)
}

const getFilePickerBuilder = (title) => {
	return nextcloudGetFilePickerBuilder(title)
}

export {
	getFilePickerBuilder,
	showError,
	showLoading,
	showSuccess,
	showUndo,
	showWarning,
}