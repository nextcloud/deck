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

import app from '../app/App.js';
// OwnCloud Click Handling
// https://doc.owncloud.org/server/8.0/developer_manual/app/css.html
app.directive('appNavigationEntryUtils', function () {
	'use strict';
	return {
		restrict: 'C',
		link: function (scope, elm) {

			var menu = elm.siblings('.app-navigation-entry-menu');
			var button = $(elm)
				.find('.app-navigation-entry-utils-menu-button button');

			button.click(function () {
				menu.toggleClass('open');
			});
			scope.$on('documentClicked', function (scope, event) {
				if (event.target !== button[0]) {
					menu.removeClass('open');
				}
			});
		}
	};
});

