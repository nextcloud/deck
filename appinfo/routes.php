<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// boards
		['name' => 'board#index', 'url' => '/boards', 'verb' => 'GET'],
		['name' => 'board#create', 'url' => '/boards', 'verb' => 'POST'],
		['name' => 'board#read', 'url' => '/boards/{boardId}', 'verb' => 'GET'],
		['name' => 'board#update', 'url' => '/boards/{boardId}', 'verb' => 'PUT'],
		['name' => 'board#delete', 'url' => '/boards/{boardId}', 'verb' => 'DELETE'],
		['name' => 'board#deleteUndo', 'url' => '/boards/{boardId}/deleteUndo', 'verb' => 'POST'],
		['name' => 'board#getUserPermissions', 'url' => '/boards/{boardId}/permissions', 'verb' => 'GET'],
		['name' => 'board#addAcl', 'url' => '/boards/{boardId}/acl', 'verb' => 'POST'],
		['name' => 'board#updateAcl', 'url' => '/boards/{boardId}/acl', 'verb' => 'PUT'],
		['name' => 'board#deleteAcl', 'url' => '/boards/{boardId}/acl/{aclId}', 'verb' => 'DELETE'],

		// stacks
		['name' => 'stack#index', 'url' => '/stacks/{boardId}', 'verb' => 'GET'],
		['name' => 'stack#create', 'url' => '/stacks', 'verb' => 'POST'],
		['name' => 'stack#update', 'url' => '/stacks/{stackId}', 'verb' => 'PUT'],
		['name' => 'stack#reorder', 'url' => '/stacks/{stackId}/reorder', 'verb' => 'PUT'],
		['name' => 'stack#delete', 'url' => '/stacks/{stackId}', 'verb' => 'DELETE'],
		['name' => 'stack#deleted', 'url' => '/{boardId}/stacks/deleted', 'verb' => 'GET'],
		['name' => 'stack#archived', 'url' => '/stacks/{boardId}/archived', 'verb' => 'GET'],

		// cards
		['name' => 'card#read', 'url' => '/cards/{cardId}', 'verb' => 'GET'],
		['name' => 'card#create', 'url' => '/cards', 'verb' => 'POST'],
		['name' => 'card#update', 'url' => '/cards/{cardId}', 'verb' => 'PUT'],
		['name' => 'card#delete', 'url' => '/cards/{cardId}', 'verb' => 'DELETE'],
		['name' => 'card#deleted', 'url' => '/{boardId}/cards/deleted', 'verb' => 'GET'],
		['name' => 'card#rename', 'url' => '/cards/{cardId}/rename', 'verb' => 'PUT'],
		['name' => 'card#reorder', 'url' => '/cards/{cardId}/reorder', 'verb' => 'PUT'],
		['name' => 'card#archive', 'url' => '/cards/{cardId}/archive', 'verb' => 'PUT'],
		['name' => 'card#unarchive', 'url' => '/cards/{cardId}/unarchive', 'verb' => 'PUT'],
		['name' => 'card#assignLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'POST'],
		['name' => 'card#removeLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'DELETE'],
		['name' => 'card#assignUser', 'url' => '/cards/{cardId}/assign', 'verb' => 'POST'],
		['name' => 'card#unassignUser', 'url' => '/cards/{cardId}/assign/{userId}', 'verb' => 'DELETE'],

		['name' => 'attachment#getAll', 'url' => '/cards/{cardId}/attachments', 'verb' => 'GET'],
		['name' => 'attachment#create', 'url' => '/cards/{cardId}/attachment', 'verb' => 'POST'],
		['name' => 'attachment#display', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'GET'],
		['name' => 'attachment#update', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'PUT'],
		// also allow to use POST for updates so we can properly access files when using application/x-www-form-urlencoded
		['name' => 'attachment#update', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'POST'],
		['name' => 'attachment#delete', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'DELETE'],
		['name' => 'attachment#restore', 'url' => '/cards/{cardId}/attachment/{attachmentId}/restore', 'verb' => 'GET'],


		// labels
		['name' => 'label#create', 'url' => '/labels', 'verb' => 'POST'],
		['name' => 'label#update', 'url' => '/labels/{labelId}', 'verb' => 'PUT'],
		['name' => 'label#delete', 'url' => '/labels/{labelId}', 'verb' => 'DELETE'],

	]
];
