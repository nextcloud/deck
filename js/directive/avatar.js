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

app.directive('avatar', function() {
	'use strict';
	return {
		restrict: 'AEC',
		transclude: true,
		replace: true,
		template: '<div class="avatardiv-container"><div class="avatardiv" data-toggle="tooltip" ng-transclude></div></div>',
		scope: { attr: '=' },
		link: function(scope, element, attr){
			scope.uid = attr.displayname;
			scope.displayname = attr.displayname;
			var value = attr.user;
			var avatardiv = $(element).find('.avatardiv');
			if(typeof attr.contactsmenu !== 'undefined' && attr.contactsmenu !== 'false') {
				avatardiv.contactsMenu(value, 0, $(element));
				avatardiv.addClass('has-contactsmenu');
			}
			if(typeof attr.tooltip !== 'undefined' && attr.tooltip !== 'false') {
				$(element).tooltip({
					title: scope.displayname,
					placement: 'top'
				});
			}
			avatardiv.avatar(value, 32, false, false, false, attr.displayname);
		},
		controller: function () {}
	};
});