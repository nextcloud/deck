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

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Board\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        // api
        ['name' => 'api#index', 'url' => '/api/', 'verb' => 'GET'],
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
        ['name' => 'card#reorder', 'url' => '/cards/reorder/', 'verb' => 'PUT'],
        ['name' => 'card#delete', 'url' => '/cards/{cardId}/', 'verb' => 'DELETE'],

        // TODO: Implement public board sharing
        ['name' => 'public#index', 'url' => '/public/board/:hash', 'verb' => 'GET'],
        ['name' => 'public#board', 'url' => '/public/board/ajax/:hash', 'verb' => 'GET'],


        // TODO: API for external access
        // ['name' => 'note_api#preflighted_cors', 'url' => '/api/v1/{path}/', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']]

    ]
];
