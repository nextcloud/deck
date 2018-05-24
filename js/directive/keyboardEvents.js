/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import app from '../app/App.js';

/* global app */
/* gloabl t */

app.directive('keyboardEvents', function ($document, $rootScope) {
	'use strict';
	return {
		restrict: 'E',
		replace: true,
		scope: true,
		link: function () {
			$document.bind('keypress', function(e) {
				if (e.target.tagName !== 'INPUT') {
					console.log(e.target.tagName);
					console.log('Got keypress:', e.key);
					$rootScope.$broadcast('keypress', e);
					$rootScope.$broadcast('keypress:' + e.key, e);
				}
				if (e.target.tagName === 'INPUT') {
					e.stopImmediatePropagation();
				}
			});
			$document.bind('keydown', function(e) {
				if (e.target.tagName !== 'INPUT') {
					console.log(e.target.tagName);
					console.log('Got keydown:', e.key);
					$rootScope.$broadcast('keydown', e);
					$rootScope.$broadcast('keydown:' + e.key, e);
				}
				if (e.target.tagName === 'INPUT') {
					e.stopImmediatePropagation();
				}
			});
		}
	};
});