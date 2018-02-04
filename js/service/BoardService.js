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

/* global app OC */
app.factory('BoardService', function (ApiService, $http, $q) {
	var BoardService = function ($http, ep, $q) {
		ApiService.call(this, $http, ep, $q);
	};
	BoardService.prototype = angular.copy(ApiService.prototype);

	BoardService.prototype.delete = function (id) {
		var deferred = $q.defer();
		var self = this;

		$http.delete(this.baseUrl + '/' + id).then(function (response) {
			self.data[id].deletedAt = response.data.deletedAt;
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Deleting ' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};

	BoardService.prototype.deleteUndo = function (id) {
		var deferred = $q.defer();
		var self = this;
		var _id = id;
		$http.post(this.baseUrl + '/' + id + '/deleteUndo').then(function (response) {
			self.data[_id].deletedAt = 0;
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Deleting ' + self.endpoint + ' failed');
		});
		return deferred.promise;
	};

	BoardService.prototype.searchUsers = function (search) {
		var deferred = $q.defer();
		var self = this;
		var searchData = {
			format: 'json',
			perPage: 4,
			itemType: [0, 1]
		};
		if (search !== "") {
			searchData.search = search;
		}
		$http({
			method: 'GET',
			url: OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees',
			params: searchData
		})
			.then(function (result) {
				var response = result.data;
				if (response.ocs.meta.statuscode !== 100) {
					deferred.reject('Error while searching for sharees');
					return;
				}
				self.sharees = [];

				var users = response.ocs.data.exact.users.concat(response.ocs.data.users);
				var groups = response.ocs.data.exact.groups.concat(response.ocs.data.groups);

				// filter out everyone who is already in the share list
				angular.forEach(users, function (item) {
					var acl = self.generateAcl(OC.Share.SHARE_TYPE_USER, item);
					var exists = false;
					angular.forEach(self.getCurrent().acl, function (acl) {
						if (acl.participant.primaryKey === item.value.shareWith) {
							exists = true;
						}
					});
					if (!exists && OC.getCurrentUser().uid !== item.value.shareWith) {
						self.sharees.push(acl);
					}
				});
				angular.forEach(groups, function (item) {
					var acl = self.generateAcl(OC.Share.SHARE_TYPE_GROUP, item);
					var exists = false;
					angular.forEach(self.getCurrent().acl, function (acl) {
						if (acl.participant.primaryKey === item.value.shareWith) {
							exists = true;
						}
					});
					if (!exists) {
						self.sharees.push(acl);
					}
				});

				deferred.resolve(self.sharees);
			}, function () {
				deferred.reject('Error while searching for sharees');
			});

		return deferred.promise;
	};

	BoardService.prototype.generateAcl = function (type, ocsItem) {
		return {
			boardId: null,
			id: null,
			owner: false,
			participant: {
				primaryKey: ocsItem.value.shareWith,
				uid: ocsItem.value.shareWith,
				displayname: ocsItem.label
			},
			permissionEdit: true,
			permissionManage: true,
			permissionShare: true,
			type: type
		};
	};

	BoardService.prototype.addAcl = function (acl) {
		var board = this.getCurrent();
		var deferred = $q.defer();
		var self = this;
		var _acl = acl;
		$http.post(this.baseUrl + '/' + acl.boardId + '/acl', _acl).then(function (response) {
			if (!board.acl || board.acl.length === 0) {
				board.acl = {};
			}
			board.acl[response.data.id] = response.data;
			self._updateUsers();
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Error creating ACL ' + _acl);
		});
		acl = null;
		return deferred.promise;
	};

	BoardService.prototype.deleteAcl = function (acl) {
		var board = this.getCurrent();
		var deferred = $q.defer();
		var self = this;
		$http.delete(this.baseUrl + '/' + acl.boardId + '/acl/' + acl.id).then(function (response) {
			delete board.acl[response.data.id];
			self._updateUsers();
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Error deleting ACL ' + acl.id);
		});
		acl = null;
		return deferred.promise;
	};

	BoardService.prototype.updateAcl = function (acl) {
		var board = this.getCurrent();
		var deferred = $q.defer();
		var self = this;
		var _acl = acl;
		$http.put(this.baseUrl + '/' + acl.boardId + '/acl', _acl).then(function (response) {
			board.acl[_acl.id] = response.data;
			self._updateUsers();
			deferred.resolve(response.data);
		}, function (error) {
			deferred.reject('Error updating ACL ' + _acl);
		});
		acl = null;
		return deferred.promise;
	};

	BoardService.prototype._updateUsers = function () {
		if (!this.getCurrent() || !this.getCurrent().acl) {
			return [];
		}
		var result = [this.getCurrent().owner];
		angular.forEach(this.getCurrent().acl, function(value, key) {
			if (value.type === OC.Share.SHARE_TYPE_USER) {
				result.push(value.participant);
			}
		});
		this.getCurrent()._users = result;
	};

	BoardService.prototype.getUsers = function () {
		if (this.getCurrent() && !this.getCurrent()._users) {
			this._updateUsers();
		}
		return this.getCurrent()._users;
	};

	BoardService.prototype.canRead = function () {
		if (!this.getCurrent() || !this.getCurrent().permissions) {
			return false;
		}
		return this.getCurrent().permissions['PERMISSION_READ'];
	};

	BoardService.prototype.canEdit = function () {
		if (!this.getCurrent() || !this.getCurrent().permissions) {
			return false;
		}
		return this.getCurrent().permissions['PERMISSION_EDIT'];
	};

	BoardService.prototype.canManage = function (board) {
		if (board !== null && board !== undefined) {
			return board.permissions['PERMISSION_MANAGE'];
		}
		if (!this.getCurrent() || !this.getCurrent().permissions) {
			return false;
		}
		return this.getCurrent().permissions['PERMISSION_MANAGE'];
	};

	BoardService.prototype.canShare = function () {
		if (!this.getCurrent() || !this.getCurrent().permissions) {
			return false;
		}
		return this.getCurrent().permissions['PERMISSION_SHARE'];
	};

	BoardService.prototype.isArchived = function () {
		if (!this.getCurrent() || this.getCurrent().archived) {
			return true;
		}
		return false;
	};

	return new BoardService($http, 'boards', $q);

});
