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

/* global app angular */
app.factory('StackService', function (ApiService, CardService, $http, $q) {
	var StackService = function ($http, ep, $q) {
		ApiService.call(this, $http, ep, $q);
	};
	StackService.prototype = angular.copy(ApiService.prototype);

	StackService.prototype.afterFetch = function(stack) {
		CardService.addAll(stack.cards);
	}

	StackService.prototype.fetchAll = function (boardId) {
		var deferred = $q.defer();
		var self = this;
		$http.get(this.baseUrl + '/' + boardId).then(function (response) {
			self.clear();
			self.addAll(response.data);
			// When loading a stack add cards to the CardService so we can fetch
			// information from there. That way we don't need to refresh the whole
			// stack data during digest if some value changes
			angular.forEach(response.data, function (entity) {
				CardService.addAll(entity.cards);
			});
			deferred.resolve(self.data);
		}, function (error) {
			deferred.reject('Error while loading stacks');
		});
		return deferred.promise;
	};

	StackService.prototype.fetchArchived = function (boardId) {
		var deferred = $q.defer();
		var self = this;
		$http.get(this.baseUrl + '/' + boardId + '/archived').then(function (response) {
			self.clear();
			self.addAll(response.data);
			angular.forEach(response.data, function (entity) {
				CardService.addAll(entity.cards);
			});
			deferred.resolve(self.data);
		}, function (error) {
			deferred.reject('Error while loading stacks');
		});
		return deferred.promise;
	};

	StackService.prototype.addCard = function (entity) {
		if (!this.data[entity.stackId].cards) {
			this.data[entity.stackId].cards = [];
		}
		this.data[entity.stackId].cards.push(entity);
	};

	StackService.prototype.reorder = function (stack, order) {
		var deferred = $q.defer();
		var self = this;
		$http.put(this.baseUrl + '/' + stack.id + '/reorder', {
			stackId: stack.id,
			order: order
		}).then(function (response) {
			angular.forEach(response.data, function (value, key) {
				var id = value.id;
				self.data[id].order = value.order;
			});
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Error while update ' + self.endpoint);
		});
		return deferred.promise;
	};

	StackService.prototype.reorderCard = function (entity, order) {
		// assign new order
		for (var i = 0, j = 0; i < this.data[entity.stackId].cards.length; i++) {
			if (this.data[entity.stackId].cards[i].id === entity.id) {
				this.data[entity.stackId].cards[i].order = order;
			}
			if (j === order) {
				j++;
			}
			if (this.data[entity.stackId].cards[i].id !== entity.id) {
				this.data[entity.stackId].cards[i].order = j++;
			}
		}
		// sort array by order
		this.data[entity.stackId].cards.sort(function (a, b) {
			if (a.order < b.order)
			{return -1;}
			if (a.order > b.order)
			{return 1;}
			return 0;
		});
	};

	StackService.prototype.updateCard = function (entity) {
		var self = this;
		var cards = this.data[entity.stackId].cards;
		for (var i = 0; i < cards.length; i++) {
			if (cards[i].id === entity.id) {
				cards[i] = entity;
			}
		}
	};
	StackService.prototype.removeCard = function (entity) {
		var self = this;
		var cards = this.data[entity.stackId].cards;
		for (var i = 0; i < cards.length; i++) {
			if (cards[i].id === entity.id) {
				cards.splice(i, 1);
			}
		}
	};

	var service = new StackService($http, 'stacks', $q);
	return service;
});
