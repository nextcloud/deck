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

import app from '../app/App';

app.directive("contenteditable", function() {
	return {
		require: "ngModel",
		link: function(scope, element, attrs, ngModel) {

			//read the text typed in the div (syncing model with the view)
			function read() {
				ngModel.$setViewValue(element.html());
			}

			//render the data now in your model into your view
			//$render is invoked when the modelvalue differs from the viewvalue
			//see documentation: https://docs.angularjs.org/api/ng/type/ngModel.NgModelController#
			ngModel.$render = function() {
				element.html(ngModel.$viewValue || "");
			};

			//do this whenever someone starts typing
			element.bind("blur keyup change", function() {
				scope.$apply(read);
			});
		}
	};
});
