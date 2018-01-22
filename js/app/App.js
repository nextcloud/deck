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

/* global angular */


angular.module('markdown', [])
	.provider('markdown', [function () {
		var opts = {};
		return {
			config: function (newOpts) {
				opts = newOpts;
			},
			$get: function () {
				return new window.showdown.Converter(opts);
			}
		};
	}])
	.filter('markdown', ['markdown', function (markdown) {
		return function (text) {
			return markdown.makeHtml(text || '');
		};
	}]);

import uirouter from '@uirouter/angularjs';
import ngsanitize from 'angular-sanitize';
import angularuiselect from 'ui-select';
import ngsortable from 'ng-sortable';
import md from 'angular-markdown-it';
import nganimate from 'angular-animate';

var app = angular.module('Deck', [
  ngsanitize,
  uirouter,
  angularuiselect,
  ngsortable, md, nganimate
]);

export default app;
