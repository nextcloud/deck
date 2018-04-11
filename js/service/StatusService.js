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

app.factory('StatusService', function () {
	// Status Helper
	var StatusService = function () {
		this.active = true;
		this.icon = 'loading';
		this.title = '';
		this.text = '';
		this.counter = 0;
	};


	StatusService.prototype.setStatus = function ($icon, $title, $text) {
		this.active = true;
		this.icon = $icon;
		this.title = $title;
		this.text = $text;
	};

	StatusService.prototype.setError = function ($title, $text) {
		this.active = true;
		this.icon = 'error';
		this.title = $title;
		this.text = $text;
		this.counter = 0;
	};

	StatusService.prototype.releaseWaiting = function () {
		if (this.counter > 0) {
			this.counter--;
		}
		if (this.counter <= 0) {
			this.active = false;
			this.counter = 0;
		}
	};

	StatusService.prototype.retainWaiting = function () {
		this.active = true;
		this.icon = 'loading';
		this.title = '';
		this.text = '';
		this.counter++;
	};

	StatusService.prototype.unsetStatus = function () {
		this.active = false;
	};

	return {
		getInstance: function () {
			return new StatusService();
		},
		/* Shared StatusService instance between both ListController instances */
		listStatus: new StatusService()
	};

});


