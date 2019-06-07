/*
* @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *  
 */

import app from '../app/App.js';

/* global OC oc_requesttoken */
export default class FileService {

	constructor (FileUploader, CardService, $rootScope, $filter) {
		this.$filter = $filter;
		this.uploader = new FileUploader();
		this.cardservice = CardService;
		this.uploader.onAfterAddingFile = this.onAfterAddingFile.bind(this);
		this.uploader.onSuccessItem = this.onSuccessItem.bind(this);
		this.uploader.onErrorItem = this.onErrorItem.bind(this);
		this.uploader.onCancelItem = this.onCancelItem.bind(this);

		this.maxUploadSize = $rootScope.config.maxUploadSize;
		this.progress = [];
		this.status = null;
	}

	reset () {
		this.status = null;
	}

	runUpload (fileItem, attachmentId) {
		this.status = null;
		fileItem.url = OC.generateUrl('/apps/deck/cards/' + fileItem.cardId + '/attachment?type=deck_file');
		if (typeof attachmentId !== 'undefined') {
			fileItem.url = OC.generateUrl('/apps/deck/cards/' + fileItem.cardId + '/attachment/' + attachmentId + '?type=deck_file');
		} else {
			fileItem.formData = [
				{
					requesttoken: oc_requesttoken,
					type: 'deck_file',
				}
			];
		}
		fileItem.headers = {requesttoken: oc_requesttoken};

		this.uploader.uploadItem(fileItem);
	}

	onAfterAddingFile (fileItem) {
		if (this.maxUploadSize > 0 && fileItem.file.size > this.maxUploadSize) {
			this.status = {
				error: t('deck', `Failed to upload {name}`, {name: fileItem.file.name}),
				message: t('deck', 'Maximum file size of {size} exceeded', {size: this.$filter('bytes')(this.maxUploadSize)})
			};
			return;
		}

		// Fetch card details before trying to upload so we can detect filename collisions properly
		let self = this;
		this.progress.push(fileItem);
		this.cardservice.fetchOne(fileItem.cardId).then(function (data) {
			let attachments = self.cardservice.get(fileItem.cardId).attachments;
			let existingFile = attachments.find((attachment) => {
				return attachment.data === fileItem.file.name;
			});
			if (typeof existingFile !== 'undefined') {
				OC.dialogs.confirm(
					t('deck', `A file with the name ${fileItem.file.name} already exists. Do you want to overwrite it?`),
					t('deck', 'File already exists'),
					function (result) {
						if (result) {
							self.runUpload(fileItem, existingFile.id);
						} else {
							let fileName = existingFile.extendedData.info.filename;
							let foundFilesMatching = attachments.filter((attachment) => {
								return attachment.extendedData.info.extension === existingFile.extendedData.info.extension
									&& attachment.extendedData.info.filename.startsWith(fileName);
							});
							let nextIndex = foundFilesMatching.length + 1;
							fileItem.file.name = fileName + ' (' + nextIndex + ').' + existingFile.extendedData.info.extension;
							self.runUpload(fileItem);
						}
					}
				);
			} else {
				self.runUpload(fileItem);
			}
		}, function (error) {
			this.progress = this.progress.filter((item) => (fileItem.file.name !== item.file.name));
		});

	}

	onSuccessItem (item, response) {
		let attachments = this.cardservice.get(item.cardId).attachments;
		let index = attachments.indexOf(attachments.find((attachment) => attachment.id === response.id));
		if (~index) {
			attachments = attachments.splice(index, 1);
		}
		this.cardservice.get(item.cardId).attachments.push(response);
		this.progress = this.progress.filter((fileItem) => (fileItem.file.name !== item.file.name));
	}

	onErrorItem (item, response) {
		this.progress = this.progress.filter((fileItem) => (fileItem.file.name !== item.file.name));
		this.status = {
			error: t('deck', `Failed to upload:`) + ' ' + item.file.name,
			message: response.message
		};
	}

	onCancelItem (item) {
		this.progress = this.progress.filter((fileItem) => (fileItem.file.name !== item.file.name));
	}

	getProgressItemsForCard (cardId) {
		return this.progress.filter((fileItem) => (fileItem.cardId === cardId));
	}

}

app.service('FileService', FileService);
