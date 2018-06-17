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

/* global OC */

class AttachmentListController {
	constructor ($scope, CardService, FileService) {
		'ngInject';
		this.cardservice = CardService;
		this.fileservice = FileService;
		this.attachments = CardService.getCurrent().attachments;
	}

	mimetypeForAttachment(attachment) {
		let url = OC.MimeType.getIconUrl(attachment.extendedData.mimetype);
		let styles = {
			'background-image': `url("${url}")`,
		};
		return styles;
	}

	attachmentUrl(attachment) {
		let cardId = this.cardservice.getCurrent().id;
		let attachmentId = attachment.id;
		return OC.generateUrl(`/apps/deck/cards/${cardId}/attachment/${attachmentId}`);
	}

	getAttachmentMarkdown(attachment) {
		const inlineMimetypes = ['image/png', 'image/jpg', 'image/jpeg'];
		let url = this.attachmentUrl(attachment);
		let filename = attachment.data;
		let insertText = `[📎 ${filename}](${url})`;
		if (inlineMimetypes.indexOf(attachment.extendedData.mimetype) > -1) {
			insertText = `![📎 ${filename}](${url})`;
		}
		return insertText;
	}

	select(attachment) {
		this.onSelect({attachment: this.getAttachmentMarkdown(attachment)});
	}

	abort() {
		this.onAbort();
	}

}

let attachmentListComponent = {
	templateUrl: '/card.attachments.html',
	controller: AttachmentListController,
	bindings: {
		isFileSelector: '<',
		attachments: '=',
		onSelect: '&',
		onAbort: '&'
	}
};
export default attachmentListComponent;
