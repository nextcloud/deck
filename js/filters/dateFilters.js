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

/* global app */
/* global OC */
/* global moment */

app.filter('relativeDateFilter', function() {
	return function (timestamp) {
		return OC.Util.relativeModifiedDate(timestamp*1000);
	};
});

app.filter('relativeDateFilterString', function() {
	return function (date) {
		return OC.Util.relativeModifiedDate(Date.parse(date));
	};
});

app.filter('dateToTimestamp', function() {
	return function (date) {
		return Date.parse(date);
	};
});

app.filter('parseDate', function() {
	return function (date) {
		if(moment(date).isValid()) {
			return moment(date).format('YYYY-MM-DD');
		}
		return '';
	};
});

app.filter('parseTime', function() {
	return function (date) {
		if(moment(date).isValid()) {
			return moment(date).format('HH:mm');
		}
		return '';
	};
});