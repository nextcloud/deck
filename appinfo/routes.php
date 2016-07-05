<?php
/**
 * ownCloud - Deck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @copyright Julius Härtl 2016
 */

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // share
        ['name' => 'share#searchUser', 'url' => '/share/search/{search}', 'verb' => 'GET'],

        // boards
        ['name' => 'board#index', 'url' => '/boards/', 'verb' => 'GET'],
        ['name' => 'board#create', 'url' => '/boards/', 'verb' => 'POST'],
        ['name' => 'board#read', 'url' => '/boards/{boardId}/', 'verb' => 'GET'],
        ['name' => 'board#update', 'url' => '/boards/', 'verb' => 'PUT'],
        ['name' => 'board#delete', 'url' => '/boards/{boardId}/', 'verb' => 'DELETE'],

        // stacks
        ['name' => 'stack#index', 'url' => '/stacks/{boardId}/', 'verb' => 'GET'],
        ['name' => 'stack#create', 'url' => '/stacks/', 'verb' => 'POST'],
        ['name' => 'stack#update', 'url' => '/stacks/', 'verb' => 'PUT'],
        ['name' => 'stack#delete', 'url' => '/stacks/{stackId}/', 'verb' => 'DELETE'],

        // cards
        ['name' => 'card#read', 'url' => '/cards/{cardId}/', 'verb' => 'GET'],
        ['name' => 'card#create', 'url' => '/cards/', 'verb' => 'POST'],
        ['name' => 'card#update', 'url' => '/cards/', 'verb' => 'PUT'],
        ['name' => 'card#rename', 'url' => '/cards/rename/', 'verb' => 'PUT'],
        ['name' => 'card#reorder', 'url' => '/cards/reorder/', 'verb' => 'PUT'],
        ['name' => 'card#delete', 'url' => '/cards/{cardId}/', 'verb' => 'DELETE'],

        // card - assign labels
        ['name' => 'card#assignLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'POST'],
        ['name' => 'card#removeLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'DELETE'],

        // TODO: card - assign user
        ['name' => 'card#assignUser', 'url' => '/cards/{cardId}/user/{labelId}', 'verb' => 'POST'],
        ['name' => 'card#removeUser', 'url' => '/cards/{cardId}/user/{labelId}', 'verb' => 'DELETE'],

        // labels
        ['name' => 'label#create', 'url' => '/labels/', 'verb' => 'POST'],
        ['name' => 'label#update', 'url' => '/labels/', 'verb' => 'PUT'],
        ['name' => 'label#delete', 'url' => '/labels/{labelId}/', 'verb' => 'DELETE'],

        // TODO: Implement public board sharing
        ['name' => 'public#index', 'url' => '/public/board/:hash', 'verb' => 'GET'],
        ['name' => 'public#board', 'url' => '/public/board/ajax/:hash', 'verb' => 'GET'],

        // TODO: API for external access
        //['name' => 'api#index', 'url' => '/api/', 'verb' => 'GET'],
        // ['name' => 'note_api#preflighted_cors', 'url' => '/api/v1/{path}/', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']]

    ]
];
