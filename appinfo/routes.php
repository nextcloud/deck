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
		['name' => 'stack#archived', 'url' => '/stacks/{boardId}/archived', 'verb' => 'GET'],

		// cards
		['name' => 'card#read', 'url' => '/cards/{cardId}', 'verb' => 'GET'],
		['name' => 'card#create', 'url' => '/cards', 'verb' => 'POST'],
		['name' => 'card#update', 'url' => '/cards/{cardId}', 'verb' => 'PUT'],
		['name' => 'card#delete', 'url' => '/cards/{cardId}', 'verb' => 'DELETE'],
		['name' => 'card#rename', 'url' => '/cards/{cardId}/rename', 'verb' => 'PUT'],
		['name' => 'card#reorder', 'url' => '/cards/{cardId}/reorder', 'verb' => 'PUT'],
		['name' => 'card#archive', 'url' => '/cards/{cardId}/archive', 'verb' => 'PUT'],
		['name' => 'card#unarchive', 'url' => '/cards/{cardId}/unarchive', 'verb' => 'PUT'],
		['name' => 'card#assignLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'POST'],
		['name' => 'card#removeLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'DELETE'],
		['name' => 'card#assignUser', 'url' => '/cards/{cardId}/assign', 'verb' => 'POST'],
		['name' => 'card#unassignUser', 'url' => '/cards/{cardId}/assign/{userId}', 'verb' => 'DELETE'],

		// labels
		['name' => 'label#create', 'url' => '/labels', 'verb' => 'POST'],
		['name' => 'label#update', 'url' => '/labels/{labelId}', 'verb' => 'PUT'],
		['name' => 'label#delete', 'url' => '/labels/{labelId}', 'verb' => 'DELETE'],

	]
];
