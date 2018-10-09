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
import CommentCollection from '../legacy/commentcollection';
import CommentModel from '../legacy/commentmodel';

const DECK_ACTIVITY_TYPE_BOARD = 'deck_board';
const DECK_ACTIVITY_TYPE_CARD = 'deck_card';

/* global OC oc_requesttoken */
class ActivityService {

	static get RESULT_PER_PAGE() { return 50; }

	constructor ($rootScope, $filter, $http, $q) {
		this.running = false;
		this.runningNewer = false;
		this.$filter = $filter;
		this.$http = $http;
		this.$q = $q;
		this.$rootScope = $rootScope;
		this.data = {};
		this.data[DECK_ACTIVITY_TYPE_BOARD] = {};
		this.data[DECK_ACTIVITY_TYPE_CARD] = {};
		this.toEnhanceWithComments = [];
		this.commentCollection = new CommentCollection();
		this.commentCollection._limit = ActivityService.RESULT_PER_PAGE;
		this.commentCollection.on('request', function() {
		}, this);
		this.commentCollection.on('sync', function(a) {
			for (let index in this.toEnhanceWithComments) {
				if (this.toEnhanceWithComments.hasOwnProperty(index)) {
					let item = this.toEnhanceWithComments[index];
					item.commentModel = this.commentCollection.get(item.subject_rich[1].comment);
					if (typeof item.commentModel !== 'undefined') {
						this.toEnhanceWithComments = this.toEnhanceWithComments.filter((entry) => entry.activity_id !== item.activity_id);
					}
				}
			}
			var firstUnread = this.commentCollection.findWhere({isUnread: true});
			if (typeof firstUnread !== 'undefined') {
				this.commentCollection.updateReadMarker();
			}
			this.notify();
		}, this);
		this.commentCollection.on('add', function(model, collection, options) {
			// we need to update the model, because it consists of client data
			// only, but the server might add meta data, e.g. about mentions
			model.fetch();
		}, this);
		this.since = {
			deck_card: {

			},
			deck_board: {

			},
		};
	}

	/**
	 * We need a event here to properly update scope once the external data from
	 * the comments backbone js code has changed
	 */
	subscribe(scope, callback) {
		let handler = this.$rootScope.$on('notify-comment-update', callback);
		scope.$on('$destroy', handler);
	}

	notify() {
		this.$rootScope.$emit('notify-comment-update');
	}

	static getUrl(type, id, since) {
		if (type === DECK_ACTIVITY_TYPE_CARD) {
			return OC.linkToOCS('apps/activity/api/v2/activity', 2) + 'filter?format=json&object_type=deck_card&object_id=' + id + '&limit=' + this.RESULT_PER_PAGE + '&since=' + since;
		}
		if (type === DECK_ACTIVITY_TYPE_BOARD) {
			return OC.linkToOCS('apps/activity/api/v2/activity', 2) + 'deck?format=json&limit=' + this.RESULT_PER_PAGE + '&since=' + since;
		}
	}

	fetchCardActivities(type, id, since) {
		this.running = true;

		this.checkData(type, id);
		const self = this;
		return this.$http.get(ActivityService.getUrl(type, id, since)).then(function (response) {
			const objects = response.data.ocs.data;

			for (let index in objects) {
				if (objects.hasOwnProperty(index)) {
					let item = objects[index];
					self.addItem(type, id, item);
					if (item.activity_id > self.since[type][id].latest) {
						self.since[type][id].latest = item.activity_id;
					}
				}
			}
			self.data[type][id].sort(function(a, b) {
				return b.activity_id - a.activity_id;
			});
			self.since[type][id].oldest = response.headers('X-Activity-Last-Given');
			self.running = false;
			return response;
		}, function (error) {
			if (error.status === 304 || error.status === 404) {
				self.since[type][id].finished = true;
			}
			self.running = false;
		});
	}

	fetchMoreActivities(type, id, success) {
		const self = this;
		this.checkData(type, id);
		if (this.running === true) {
			return this.runningPromise;
		}
		if (!this.since[type][id].finished) {
			this.runningPromise = this.fetchCardActivities(type, id, this.since[type][id].oldest);
			this.runningPromise.then(function() {
				if (type === 'deck_card') {
					self.commentCollection.fetchNext();
				}
			});
			return this.runningPromise;
		}
		return Promise.reject();
	}
	checkData(type, id) {
		if (!Array.isArray(this.data[type][id])) {
			this.data[type][id] = [];
		}
		if (typeof this.since[type][id] === 'undefined') {
			this.since[type][id] = {
				latest: 0,
				oldestCatchedUp: false,
				oldest: '0',
				finished: false,
			};
		}
	}

	addItem(type, id, item) {
		const self = this;
		const existingEntry = this.data[type][id].findIndex((entry) => { return entry.activity_id === item.activity_id; });
		if (existingEntry !== -1) {
			return;
		}
		/** check if the fetched item from all deck activities is actually related */
		const isUnrelatedBoard = (item.object_type === DECK_ACTIVITY_TYPE_BOARD && item.object_id !== id);
		const isUnrelatedCard = (item.object_type === DECK_ACTIVITY_TYPE_CARD && item.subject_rich[1].board && item.subject_rich[1].board.id !== id);
		if (type === DECK_ACTIVITY_TYPE_BOARD && (isUnrelatedBoard || isUnrelatedCard)) {
			return;
		}
		item.timestamp = new Date(item.datetime).getTime();
		item.type = 'activity';
		if (item.subject_rich[1].comment) {
			item.type = 'comment';
			item.commentModel = this.commentCollection.get(item.subject_rich[1].comment);
			if (typeof item.commentModel === 'undefined') {
				this.toEnhanceWithComments.push(item);
			}
		}

		this.data[type][id].push(item);
	}

	/**
	 * Fetch newer activities starting from the latest ones that are in cache
	 *
	 * @param type
	 * @param id
	 */
	fetchNewerActivities(type, id) {
		if (this.since[type][id].latest === 0) {
			return Promise.resolve();
		}
		let self = this;
		return this.fetchNewer(type, id).then(function() {
			return self.fetchNewerActivities(type, id);
		});
	}

	fetchNewer(type, id) {
		const deferred = this.$q.defer();
		this.running = true;
		this.runningNewer = true;
		const self = this;
		this.$http.get(ActivityService.getUrl(type, id, this.since[type][id].latest) + '&sort=asc').then(function (response) {
			let objects = response.data.ocs.data;

			let data = [];
			for (let index in objects) {
				if (objects.hasOwnProperty(index)) {
					let item = objects[index];
					self.addItem(type, id, item);
				}
			}
			self.data[type][id].sort(function(a, b) {
				return b.activity_id - a.activity_id;
			});
			self.since[type][id].latest = response.headers('X-Activity-Last-Given');
			self.data[type][id] = data.concat(self.data[type][id]);
			self.running = false;
			self.runningNewer = false;
			deferred.resolve(objects);
		}, function (error) {
			self.runningNewer = false;
			self.running = false;
		});
		return deferred.promise;
	}

	getData(type, id) {
		if (!Array.isArray(this.data[type][id])) {
			return [];
		}
		return this.data[type][id];
	}

	loadComments(id) {
		this.commentCollection.reset();
		this.commentCollection.setObjectId(id);
	}

}

app.service('ActivityService', ActivityService);

export default ActivityService;
export {DECK_ACTIVITY_TYPE_BOARD, DECK_ACTIVITY_TYPE_CARD};
