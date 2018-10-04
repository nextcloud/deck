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

/* global OC OCA */

import CommentCollection from '../legacy/commentcollection';
import CommentModel from '../legacy/commentmodel';

class ActivityController {
	constructor ($scope, CardService, ActivityService) {
		'ngInject';
		this.cardservice = CardService;
		this.activityservice = ActivityService;
		this.$scope = $scope;
		this.type = '';
		this.loading = false;
		this.$scope.newComment = '';

		const self = this;
		this.$scope.$watch(function () {
			return self.element.id;
		}, function (params) {
			if (self.getData(self.element.id).length === 0) {
				self.activityservice.loadComments(self.element.id);
				self.loading = true;
				self.fetchUntilResults();
			}
			self.activityservice.fetchNewerActivities(self.type, self.element.id).then(function () {});
		}, true);
	}


	postComment() {
		const self = this;
		var model = this.activityservice.commentCollection.create({
			actorId: OC.getCurrentUser().uid,
			actorDisplayName: OC.getCurrentUser().displayName,
			actorType: 'users',
			verb: 'comment',
			message: self.$scope.newComment,
			creationDateTime: (new Date()).toUTCString()
		}, {
			at: 0,
			// wait for real creation before adding
			wait: true,
			success: function() {
				console.log("SUCCESS");
				self.$scope.newComment = '';
				self.activityservice.fetchNewerActivities(self.type, self.element.id).then(function () {});
			},
			error: function() {

			}
		});
	}

	updateComment(item) {
		let newMessage = 'Edited at ' + (new Date());
		item.commentModel.save({
			message: newMessage,
		});
		item.message = newMessage;

	}

	deleteComment() {

	}

	getCommentDetails() {}



	getData(id) {
		return this.activityservice.getData(this.type, id);
	}

	parseMessage(subject, parameters) {
		OCA.Activity.RichObjectStringParser._userLocalTemplate = '<span class="avatar-name-wrapper"><avatar ng-attr-contactsmenu ng-attr-tooltip ng-attr-user="{{ id }}" ng-attr-displayname="{{name}}" ng-attr-size="16"></avatar> {{ name }}</span>';
		return OCA.Activity.RichObjectStringParser.parseMessage(subject, parameters);
	}

	fetchUntilResults () {
		const self = this;
		let dataLengthBefore = self.getData(self.element.id).length;
		let _executeFetch = function() {
			let promise = self.activityservice.fetchMoreActivities(self.type, self.element.id);
			promise.then(function (data) {
				let dataLengthAfter = self.getData(self.element.id).length;
				if (data !== null && (dataLengthAfter <= dataLengthBefore || dataLengthAfter < self.activityservice.RESULT_PER_PAGE)) {
					_executeFetch();
				} else {
					self.loading = false;
				}
			}, function () {
				self.loading = false;
				self.$scope.$apply();
			});

		};
		_executeFetch();
	}

	getComments() {
		return this.activityservice.comments;
	}

	getActivityStream() {
		const self = this;
		let activities = this.activityservice.getData(this.type, this.element.id);
		this.data = [];
		for (let i in activities) {
			let activity = activities[i];
			activity.timelineType = 'activity';
			activity.timelineTimestamp = activity.timestamp;
			this.data.push(activity);
		}
		let sorted = this.data.sort((a, b) => b.timelineTimestamp - a.timelineTimestamp);
		return sorted;
	}

	page() {
		if (!this.activityservice.since[this.type][this.element.id].finished) {
			this.loading = true;
			this.fetchUntilResults();
		} else {
			this.loading = false;
		}
	}

	loadingNewer() {
		return this.activityservice.runningNewer;
	}

}

let activityComponent = {
	templateUrl: OC.linkTo('deck', 'templates/part.card.activity.html'),
	controller: ActivityController,
	bindings: {
		type: '@',
		element: '='
	}
};
export default activityComponent;
