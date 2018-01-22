/*
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
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

/* global app */
/* gloabl t */
/* global moment */

app.directive('datepicker', function () {
	'use strict';
	return {
		link: function (scope, elm, attr) {
			return elm.datepicker({
				dateFormat: 'yy-mm-dd',
				onSelect: function(date, inst) {
					scope.setDuedate(moment(date));
					scope.$apply();
				},
				beforeShow: function(input, inst) {
					var dp, marginLeft;
					dp = $(inst).datepicker('widget');
					marginLeft = -Math.abs($(input).outerWidth() - dp.outerWidth()) / 2 + 'px';
					dp.css({
						'margin-left': marginLeft
					});
					$('div.ui-datepicker:before').css({
						'left': 100 + 'px'
					});
					return $('.hasDatepicker').datepicker();
				},
				minDate: null
			});
		}
	};
});