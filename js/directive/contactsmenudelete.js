/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
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
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import app from '../app/App.js';

app.directive('contactsmenudelete', function() {
	'use strict';
	return {
		restrict: 'A',
		priority: 1,
		link: function(scope, element, attr){
			var user = attr.user;
			var menu = $(element).parent().find('.contactsmenu-popover');
			if (oc_current_user === user) {
				menu.children(':first').remove();
			}
			var menuEntry = $('<li><a><span class="icon icon-delete"></span><span>' + t('deck', 'Remove user from card') + '</span></a></li>');
			menuEntry.on('click', function () {
				scope.removeAssignedUser(user);
			});
			$(menu).append(menuEntry);
		}
	};
});