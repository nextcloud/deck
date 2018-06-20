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

	constructor ($http, FileUploader, CardService) {
		this.uploader = new FileUploader();
		this.cardservice = CardService;
		this.uploader.onAfterAddingFile = this.onAfterAddingFile.bind(this);
		this.uploader.onSuccessItem = this.onSuccessItem.bind(this);
	}


	runUpload (fileItem, attachmentId) {
		fileItem.url = OC.generateUrl('/apps/deck/cards/' + fileItem.cardId + '/attachment');
		if (typeof attachmentId !== 'undefined') {
			fileItem.url = OC.generateUrl('/apps/deck/cards/' + fileItem.cardId + '/attachment/' + attachmentId);
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
		// Fetch card details before trying to upload so we can detect filename collisions properly
		let self = this;
		this.cardservice.fetchOne(fileItem.cardId).then(function (data) {
			let attachments = self.cardservice.get(fileItem.cardId).attachments;
			let existingFile = attachments.find((attachment) => {
				return attachment.data === fileItem.file.name;
			});
			if (typeof existingFile !== 'undefined') {
				OC.dialogs.confirm(
					`A file with the name ${fileItem.file.name} already exists. Do you want to overwrite it?`,
					'File already exists',
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

		});

	}

	onSuccessItem (item, response) {
		let attachments = this.cardservice.get(item.cardId).attachments;
		let index = attachments.indexOf(attachments.find((attachment) => attachment.id === response.id));
		if (~index) {
			attachments = attachments.splice(index, 1);
		}
		this.cardservice.get(item.cardId).attachments.push(response);
	}

}

app.service('FileService', FileService);