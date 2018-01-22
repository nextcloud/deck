/*
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

/* global app oc_requesttoken markdownitLinkTarget */

import app from './App.js';
import md from 'angular-markdown-it';
import markdownitLinkTarget from 'markdown-it-link-target';

app.config(function ($provide, $interpolateProvider, $httpProvider, $urlRouterProvider, $stateProvider, $compileProvider, markdownItConverterProvider) {
	'use strict';
	$httpProvider.defaults.headers.common.requesttoken = oc_requesttoken;

	$compileProvider.debugInfoEnabled(true);

	markdownItConverterProvider.use(markdownitLinkTarget, {
		breaks: true,
		linkify: true,
		xhtmlOut: true
	});

	$urlRouterProvider.otherwise('/');

	$stateProvider
		.state('list', {
			url: '/:filter',
			templateUrl: '/boardlist.mainView.html',
			controller: 'ListController',
			reloadOnSearch: false,
			params: {
				filter: {value: '', dynamic: true}
			}
		})
		.state('board', {
			url: '/board/:boardId/:filter',
			templateUrl: '/board.html',
			controller: 'BoardController',
			params: {
				filter: {value: '', dynamic: true}
			}
		})
		.state('board.detail', {
			url: '/detail/',
			reloadOnSearch: false,
			params: {
				tab: {value: 0, dynamic: true},
			},
			views: {
				'sidebarView': {
					templateUrl: '/board.sidebarView.html'
				}
			}
		})
		.state('board.card', {
			url: '/card/:cardId',
			views: {
				'sidebarView': {
					templateUrl: '/card.sidebarView.html',
					controller: 'CardController'
				}
			}
		});

});
