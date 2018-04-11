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
import app from './App.js';

/* global Snap */
app.run(function ($document, $rootScope, $transitions, BoardService) {
	'use strict';
	$document.click(function (event) {
		$rootScope.$broadcast('documentClicked', event);
	});
	$transitions.onEnter({from: 'list'}, function ($state, $transition$) {
		BoardService.unsetCurrrent();
	});
	$transitions.onEnter({to: 'list'}, function ($state, $transition$) {
		BoardService.unsetCurrrent();
		document.title = "Deck - " + oc_defaults.name;
	});
	$transitions.onEnter({to: 'board.card'}, function ($state, $transition$) {
		$rootScope.sidebar.show = true;
	});
	$transitions.onEnter({to: 'board.detail'}, function ($state, $transition$) {
		$rootScope.sidebar.show = true;
	});
	$transitions.onEnter({to: 'board'}, function ($state) {
		$rootScope.sidebar.show = false;
	});
	$transitions.onExit({from: 'board.card'}, function ($state) {
		$rootScope.sidebar.show = false;
	});
	$transitions.onExit({from: 'board.detail'}, function ($state) {
		$rootScope.sidebar.show = false;
	});

	$('link[rel="shortcut icon"]').attr(
		'href',
		OC.filePath('deck', 'img', 'app-512.png')
	);

	$('#app-navigation-toggle').off('click');
	// App sidebar on mobile
	var snapper = new Snap({
		element: document.getElementById('app-content'),
		disable: 'right',
		maxPosition: 250,
		touchToDrag: false
	});

	$('#app-navigation-toggle').click(function () {
		if ($(window).width() > 768) {
			$('#app-navigation').toggle('hidden');
		} else {
			if (snapper.state().state === 'left') {
				snapper.close();
			} else {
				snapper.open('left');
			}
		}
	});
	// Select all elements with data-toggle="tooltips" in the document
	$('body').tooltip({
		selector: '[data-toggle="tooltip"]'
	});

});
