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

/** global: oc_defaults */
app.factory('ApiService', function ($http, $q) {
	var ApiService = function (http, endpoint) {
		this.endpoint = endpoint;
		this.baseUrl = OC.generateUrl('/apps/deck/' + endpoint);
		this.http = http;
		this.q = $q;
		this.data = {};
		this.deleted = {};
		this.id = null;
		this.sorted = [];
	};

	ApiService.prototype.fetchAll = function () {
		var deferred = $q.defer();
		var self = this;
		$http.get(this.baseUrl).then(function (response) {
			var objects = response.data;
			objects.forEach(function (obj) {
				self.data[obj.id] = obj;
			});
			deferred.resolve(self.data);
		}, function (error) {
			deferred.reject('Fetching ' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};

	ApiService.prototype.fetchDeleted = function (scopeId) {
		var deferred = $q.defer();
		var self = this;
		$http.get(this.baseUrl + '/deleted/' + scopeId).then(function (response) {
	        	var objects = response.data;
						objects.forEach(function (obj) {
							self.deleted[obj.id] = obj;
						});
	        	deferred.resolve(objects);
		}, function (error) {
	        	deferred.reject('Fetching ' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};


	ApiService.prototype.fetchOne = function (id) {

		this.id = id;
		var deferred = $q.defer();

		if (id === undefined) {
			return deferred.promise;
		}

		var self = this;
		$http.get(this.baseUrl + '/' + id).then(function (response) {
			var data = response.data;
			if (self.data[data.id] === undefined) {
				self.data[data.id] = response.data;
			}
			$.each(response.data, function (key, value) {
				self.data[data.id][key] = value;
			});
			deferred.resolve(response.data);

		}, function (error) {
			deferred.reject('Fetching ' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};

	ApiService.prototype.create = function (entity) {
		var deferred = $q.defer();
		var self = this;
		$http.post(this.baseUrl, entity).then(function (response) {
			self.add(response.data);
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Fetching' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};

	ApiService.prototype.update = function (entity) {
		var deferred = $q.defer();
		var self = this;
		$http.put(this.baseUrl + '/' + entity.id, entity).then(function (response) {
			self.add(response.data);
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Updating ' + self.endpoint + ' failed');
		});
		return deferred.promise;

	};

	ApiService.prototype.delete = function (id) {
		var deferred = $q.defer();
		var self = this;

		$http.delete(this.baseUrl + '/' + id).then(function (response) {
			self.deleted[id] = self.data[id];
			self.remove(id);
			deferred.resolve(response.data);

		}, function (error) {
			deferred.reject('Deleting ' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};

	ApiService.prototype.undoDelete = function(entity) {
		var self = this;
		entity.deletedAt = 0;

		var promise = this.update(entity);

		promise.then(function() {
			self.data[entity.id] = entity;
			self.remove(entity.id, 'deleted');
		});

		return promise;
	};

	// methods for managing data
	ApiService.prototype.clear = function () {
		this.data = {};
	};
	ApiService.prototype.add = function (entity) {
		var element = this.data[entity.id];
		if (element === undefined) {
			this.data[entity.id] = entity;
		} else {
			Object.keys(entity).forEach(function (key) {
				if (entity[key] !== null && element[key] !== entity[key]) {
					element[key] = entity[key];
				}
			});
			element.status = {};
		}
	};
	ApiService.prototype.remove = function (id, collection = 'data') {
		if (this[collection][id] !== undefined) {
			delete this[collection][id];
		}
	};
	ApiService.prototype.addAll = function (entities) {
		var self = this;
		angular.forEach(entities, function (entity) {
			self.add(entity);
		});
	};

	ApiService.prototype.getCurrent = function () {
		return this.data[this.id];
	};

	ApiService.prototype.unsetCurrrent = function () {
		this.id = null;
	};


	ApiService.prototype.getData = function () {
		return $.map(this.data, function (value, index) {
			return [value];
		});
	};

	ApiService.prototype.getAll = function () {
		return this.data;
	};

	ApiService.prototype.get = function (id) {
		return this.data[id];
	};

	ApiService.prototype.getName = function () {
		var funcNameRegex = /function (.{1,})\(/;
		var results = (funcNameRegex).exec((this).constructor.toString());
		return (results && results.length > 1) ? results[1] : '';
	};

	return ApiService;

});
