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

app.directive('appPopoverMenuUtils', function () {
	'use strict';
	return {
		restrict: 'C',
		link: function (scope, elm) {
			var menu = elm.find('.popovermenu');
			var button = elm.find('button');
			button.click(function (e) {
				var popovermenus = $('.popovermenu');
				var shouldShow = menu.hasClass('hidden');
				popovermenus.addClass('hidden');
				if (shouldShow) {
					menu.toggleClass('hidden');
				}
				e.stopPropagation();
			});
			scope.$on('documentClicked', function (scope, event) {
				/* prevent closing popover if target has no-close class */
				if (event.target !== button && !$(event.target).hasClass('no-close')) {
					menu.addClass('hidden');
				}
			});
		}
	};
});
