/*
 * @copyright Copyright (c) 2018 Oskar Kurz <oskar.kurz@gmail.com>
 *
 * @author Oskar Kurz <oskar.kurz@gmail.com>
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

/* global oc_defaults OC */
app.controller('ColorPickerController', ['$scope', function($scope) {
    $scope.hashedColor = '';

    $scope.setColor = function(object,color) {
        object.color = color;
        object.hashedColor = '#' + color;

        return object;
    }

    $scope.setHashedColor = function(object) {
        object.color = object.hashedColor.substr(1);
        return object;
    }

    $scope.getCustomBackground = function(color) {
        return { 'background-color': color };
    };
}]);
