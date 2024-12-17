<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Attachment;
use OCP\AppFramework\Http\Response;

/**
 * Interface IAttachmentService
 *
 * Implement this interface to extend the default attachment behaviour
 * This interface allows to extend/reduce the data stored with an attachment,
 * as well as rendering a custom output per attachment type
 *
 */
interface IAttachmentService {

	/**
	 * Add extended data to the returned data of an attachment
	 *
	 * @param Attachment $attachment
	 * @return mixed
	 */
	public function extendData(Attachment $attachment);

	/**
	 * Display the attachment
	 *
	 * TODO: Move to IAttachmentDisplayService for better separation
	 *
	 * @param Attachment $attachment
	 * @return Response
	 */
	public function display(Attachment $attachment);

	/**
	 * Create a new attachment
	 *
	 * This method will be called before inserting the attachment entry in the database
	 *
	 * @param Attachment $attachment
	 */
	public function create(Attachment $attachment);

	/**
	 * Update an attachment with custom data
	 *
	 * This method will be called before updating the attachment entry in the database
	 *
	 * @param Attachment $attachment
	 */
	public function update(Attachment $attachment);

	/**
	 * Delete an attachment
	 *
	 * This method will be called before removing the attachment entry from the database
	 *
	 * @param Attachment $attachment
	 */
	public function delete(Attachment $attachment);

	/**
	 * Should undo be allowed and the delete action be done by a background job
	 *
	 * @return bool
	 */
	public function allowUndo();

	/**
	 * Mark an attachment as deleted
	 *
	 * @param Attachment $attachment
	 */
	public function markAsDeleted(Attachment $attachment);
}
