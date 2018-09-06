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

/* global OC */

class ActivityController {
	constructor ($scope, CardService, ActivityService) {
		'ngInject';
		this.cardservice = CardService;
		this.activityservice = ActivityService;
		this.$scope = $scope;
		this.type = '';
		this.loading = false;

		const self = this;
		this.$scope.$watch(function () {
			return self.element.id;
		}, function (params) {
			if (self.getData(self.element.id).length === 0) {
				self.loading = true;
				self.fetchUntilResults();
			}
			self.activityservice.fetchNewerActivities(self.type, self.element.id).then(function () {});
		}, true);
	}

	getData(id) {
		return this.activityservice.getData(this.type, id);
	}

	parseMessage(subject, parameters) {
		OCA.Activity.RichObjectStringParser._userLocalTemplate = '<avatar ng-attr-contactsmenu ng-attr-tooltip ng-attr-user="{{ id }}" ng-attr-displayname="{{name}}"></avatar>';
		return OCA.Activity.RichObjectStringParser.parseMessage(subject, parameters);
	}

	fetchUntilResults () {
		const self = this;
		let dataLengthBefore = self.getData(self.element.id).length;
		let _executeFetch = function() {
			let promise = self.activityservice.fetchMoreActivities(self.type, self.element.id);
			if (Promise.resolve(promise) === promise) {
				promise.then(function (data) {
					let dataLengthAfter = self.getData(self.element.id).length;
					if (data !== null || dataLengthAfter <= dataLengthBefore || dataLengthAfter < 5) {
						_executeFetch();
					} else {
						self.loading = false;
						self.$scope.$apply();
					}
				}, function () {
					self.loading = false;
					self.$scope.$apply();
				});
			}
		};
		_executeFetch();
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
