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

/* global app moment */
import app from '../app/App.js';

app.controller('CardController', function ($scope, $rootScope, $sce, $location, $stateParams, $state, $interval, $timeout, $filter, BoardService, CardService, StackService, StatusService, markdownItConverter, FileService) {
	$scope.sidebar = $rootScope.sidebar;
	$scope.status = {
		lastEdit: 0,
		lastSave: Date.now()
	};

	$scope.cardservice = CardService;
	$scope.fileservice = FileService;
	$scope.cardId = $stateParams.cardId;

	$scope.statusservice = StatusService.getInstance();
	$scope.boardservice = BoardService;

	$scope.isArray = angular.isArray;
	// workaround for $stateParams changes not being propagated
	$scope.$watch(function() {
		return $state.params;
	}, function (params) {
		$scope.params = params;
	}, true);
	$scope.params = $state.params;
	$scope.mimetypeForAttachment = function(attachment) {
		let url = OC.MimeType.getIconUrl(attachment.extendedData.mimetype);
		let style = {
			'background-image': `url("${url}")`,
		};
		return style;
	};
	$scope.attachmentUrl = function(attachment) {
		let cardId = $scope.cardservice.getCurrent().id;
		let attachmentId = attachment.id;
		return OC.generateUrl(`/apps/deck/cards/${cardId}/attachment/${attachmentId}`);
	};

	$scope.statusservice.retainWaiting();

	$scope.description = function() {
		return $scope.rendered;
	};

	$scope.updateMarkdown = function(content) {
		// only trust the html from markdown-it-checkbox
		$scope.rendered = $sce.trustAsHtml(markdownItConverter.render(content || ''));
	};

	CardService.fetchOne($scope.cardId).then(function (data) {
		$scope.statusservice.releaseWaiting();
		$scope.archived = CardService.getCurrent().archived;
		$scope.updateMarkdown(CardService.getCurrent().description);
	}, function (error) {
	});

	$scope.cardRenameShow = function () {
		if ($scope.archived || !BoardService.canEdit())
		{return false;}
		else {
			$scope.status.cardRename = true;
		}
	};

	$scope.toggleCheckbox = function (id) {
		$('#markdown input[type=checkbox]').attr('disabled', true);
		$scope.status.edit = angular.copy(CardService.getCurrent());
		var reg = /\[(X|\s|\_|\-)\]\s(.*)/ig;
		var nth = 0;
		$scope.status.edit.description = $scope.status.edit.description.replace(reg, function (match, i, original) {
			if (nth++ === id) {
				var result;
				if (match.match(/^\[\s\]/i)) {
					result = match.replace(/\[\s\]/i, '[x]');
				}
				if (match.match(/^\[x\]/i)) {
					result = match.replace(/\[x\]/i, '[ ]');
				}
				return result;
			}
			return match;
		});
		CardService.update($scope.status.edit).then(function (data) {
			var header = $('.section-header-tabbed .tabDetails');
			header.find('.save-indicator.unsaved').hide();
			header.find('.save-indicator.saved').fadeIn(250).fadeOut(1000);
		});
		$('#markdown input[type=checkbox]').removeAttr('disabled');

	};
	$scope.clickCardDescription = function ($event) {
		var checkboxId = $($event.target).data('id');
		if ($event.target.tagName === 'LABEL') {
			$scope.toggleCheckbox(checkboxId);
			return;
		}
		if ($event.target.tagName === 'INPUT') {
			$scope.toggleCheckbox(checkboxId);
			return;
		}
		if (BoardService.isArchived() || CardService.getCurrent().archived) {
			return false;
		}
		if ($scope.card.archived || !$scope.boardservice.canEdit()) {
			return false;
		}
		$scope.status.cardEditDescription = true;
		$scope.status.edit = angular.copy(CardService.getCurrent());
		return true;
	};
	$scope.cardEditDescriptionChanged = function ($event) {
		$scope.status.lastEdit = Date.now();
		var header = $('.section-header-tabbed .tabDetails');
		header.find('.save-indicator.unsaved').show();
		header.find('.save-indicator.saved').hide();
	};
	$interval(function() {
		var currentTime = Date.now();
		var timeSinceEdit = currentTime-$scope.status.lastEdit;
		if (timeSinceEdit > 1000 && $scope.status.lastEdit > $scope.status.lastSave && !$scope.status.saving) {
			$scope.status.lastSave = currentTime;
			$scope.status.saving = true;
			var header = $('.section-header-tabbed .tabDetails');
			header.find('.save-indicator.unsaved').fadeIn(500);
			CardService.update($scope.status.edit).then(function (data) {
				var header = $('.section-header-tabbed .tabDetails');
				header.find('.save-indicator.unsaved').hide();
				header.find('.save-indicator.saved').fadeIn(250).fadeOut(1000);
				$scope.status.saving = false;
			});
		}
	}, 500, 0, false);

	// handle rename to update information on the board as well
	$scope.cardRename = function (card) {
		CardService.rename(card).then(function (data) {
			$scope.status.renameCard = false;
		});
	};
	$scope.cardUpdate = function (card) {
		CardService.update(card).then(function (data) {
			$scope.status.cardEditDescription = false;
			var header = $('.section-header-tabbed .tabDetails');
			header.find('.save-indicator.unsaved').hide();
			header.find('.save-indicator.saved').fadeIn(500).fadeOut(1000);
		});
	};

	$scope.labelAssign = function (element, model) {
		CardService.assignLabel($scope.cardId, element.id).then(function (data) {
		});
	};

	$scope.labelRemove = function (element, model) {
		CardService.removeLabel($scope.cardId, element.id).then(function (data) {
		});
	};

	$scope.setDuedate = function (duedate) {
		var element = CardService.getCurrent();
		var newDate = moment(element.duedate);
		if(!newDate.isValid()) {
			newDate = moment();
		}
		newDate.date(duedate.date());
		newDate.month(duedate.month());
		newDate.year(duedate.year());
		element.duedate = newDate.toISOString();
		CardService.update(element);
	};
	$scope.setDuedateTime = function (time) {
		var element = CardService.getCurrent();
		var newDate = moment(element.duedate);
		if(!newDate.isValid()) {
			newDate = moment();
		}
		newDate.hour(time.hour());
		newDate.minute(time.minute());
		element.duedate = newDate.toISOString();
		CardService.update(element);
	};

	$scope.resetDuedate = function () {
		var element = CardService.getCurrent();
		element.duedate = null;
		CardService.update(element);
	};
	
	/**
	 * Show ui-select field when clicking the add button
	 */
	$scope.toggleAssignUser = function() {
		$scope.status.showAssignUser = !$scope.status.showAssignUser;
		if ($scope.status.showAssignUser === true) {
			$timeout(function () {
				$('#assignUserSelect').find('a').click();
			});
		}
	};

	/**
	 * Hide ui-select when select list is closed
	 */
	$scope.assingUserOpenClose = function(isOpen) {
		$scope.status.showAssignUser = isOpen;
	};

	$scope.addAssignedUser = function(item) {
		CardService.assignUser(CardService.getCurrent(), item.uid).then(function (data) {
		});
		$scope.status.showAssignUser = false;
	};

	$scope.removeAssignedUser = function(uid) {
		CardService.unassignUser(CardService.getCurrent(), uid).then(function (data) {
		});
	};

	$scope.labelStyle = function (color) {
		return {
			'background-color': '#' + color,
			'color': $filter('textColorFilter')(color)
		};
	};

});
