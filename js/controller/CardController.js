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

app.controller('CardController', function ($scope, $rootScope, $routeParams, $location, $stateParams, $interval, $timeout, BoardService, CardService, StackService, StatusService) {
	$scope.sidebar = $rootScope.sidebar;
	$scope.status = {
		lastEdit: 0,
		lastSave: Date.now()
	};

	$scope.cardservice = CardService;
	$scope.cardId = $stateParams.cardId;

	$scope.statusservice = StatusService.getInstance();
	$scope.boardservice = BoardService;

	$scope.statusservice.retainWaiting();

	CardService.fetchOne($scope.cardId).then(function (data) {
		$scope.statusservice.releaseWaiting();
		$scope.archived = CardService.getCurrent().archived;
	}, function (error) {
	});

	$scope.cardRenameShow = function () {
		if ($scope.archived || !BoardService.canEdit())
		{return false;}
		else {
			$scope.status.cardRename = true;
		}
	};
	$scope.cardEditDescriptionShow = function ($event) {
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
		var header = $('.section-header.card-description');
		header.find('.save-indicator.unsaved').show();
		header.find('.save-indicator.saved').hide();
	};
	$interval(function() {
		var currentTime = Date.now();
		var timeSinceEdit = currentTime-$scope.status.lastEdit;
		if (timeSinceEdit > 1000 && $scope.status.lastEdit > $scope.status.lastSave) {
			$scope.status.lastSave = currentTime;
			var header = $('.section-header.card-description');
			header.find('.save-indicator.unsaved').fadeIn(500);
			CardService.update($scope.status.edit).then(function (data) {
				var header = $('.section-header.card-description');
				header.find('.save-indicator.unsaved').hide();
				header.find('.save-indicator.saved').fadeIn(250).fadeOut(1000);
			});
		}
	}, 500);

	// handle rename to update information on the board as well
	$scope.cardRename = function (card) {
		CardService.rename(card).then(function (data) {
			StackService.updateCard(card);
			$scope.status.renameCard = false;
		});
	};
	$scope.cardUpdate = function (card) {
		CardService.update(card).then(function (data) {
			$scope.status.cardEditDescription = false;
			var header = $('.section-content.card-description');
			header.find('.save-indicator.unsaved').hide();
			header.find('.save-indicator.saved').fadeIn(500).fadeOut(1000);
			StackService.updateCard(card);
		});
	};

	$scope.labelAssign = function (element, model) {
		CardService.assignLabel($scope.cardId, element.id).then(function (data) {
			StackService.updateCard(CardService.getCurrent());
		});
	};

	$scope.labelRemove = function (element, model) {
		CardService.removeLabel($scope.cardId, element.id).then(function (data) {
			StackService.updateCard(CardService.getCurrent());
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
		StackService.updateCard(element);
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
		StackService.updateCard(element);
	};

	$scope.resetDuedate = function () {
		var element = CardService.getCurrent();
		element.duedate = null;
		CardService.update(element);
		StackService.updateCard(element);
	};
	
	/**
	 * Show ui-select field when clicking the add button
	 */
	$scope.showAssignUser = function() {
		$scope.status.showAssignUser = true;
		$timeout(function() {
			$('#assignUserSelect').find('a').click();
		});
	};

	/**
	 * Hide ui-select when select list is closed
	 */
	$scope.assingUserOpenClose = function(isOpen) {
		if (!isOpen) {
			$scope.status.showAssignUser = false;
		}
	};

	$scope.addAssignedUser = function(item) {
		CardService.assignUser(CardService.getCurrent(), item.uid).then(function (data) {
			StackService.updateCard(CardService.getCurrent());
		});
		$scope.status.showAssignUser = false;
	};

	$scope.removeAssignedUser = function(item) {
		CardService.unassignUser(CardService.getCurrent(), item.participant.uid).then(function (data) {
			StackService.updateCard(CardService.getCurrent());
		});
	};

});