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

app.controller('BoardController', function ($rootScope, $scope, $stateParams, StatusService, BoardService, StackService, CardService, LabelService, $state, $transitions, $filter) {

	$scope.sidebar = $rootScope.sidebar;

	$scope.id = $stateParams.boardId;
	$scope.status = {};
	$scope.newLabel = {};
	$scope.status.boardtab = $stateParams.detailTab;

	$scope.stackservice = StackService;
	$scope.boardservice = BoardService;
	$scope.cardservice = CardService;
	$scope.statusservice = StatusService.getInstance();
	$scope.labelservice = LabelService;
	$scope.defaultColors = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];

	$scope.search = function (searchText) {
		$scope.searchText = searchText;
		$scope.refreshData();
	};

	$scope.board = BoardService.getCurrent();
	StackService.clear(); //FIXME: Is this still needed?
	$scope.statusservice.retainWaiting();
	$scope.statusservice.retainWaiting();

	// FIXME: ugly solution for archive
	$scope.$state = $stateParams;
	$scope.filter = $stateParams.filter;
	$scope.$watch('$state.filter', function (name) {
		$scope.filter = name;
	});
	$scope.switchFilter = function (filter) {
		$state.go('.', {filter: filter}, {notify: false});
		$scope.filter = filter;
	};
	$scope.$watch('filter', function (name) {
		if (name === "archive") {
			$scope.loadArchived();
		} else {
			$scope.loadDefault();
		}
	});


	$scope.stacksData = StackService;
	$scope.stacks = {};
	$scope.$watch('stacksData', function (value) {
		$scope.refreshData();
	}, true);
	$scope.refreshData = function () {
		if ($scope.filter === "archive") {
			$scope.filterData('-lastModified', $scope.searchText);
		} else {
			$scope.filterData('order', $scope.searchText);
		}
	};
	$scope.checkCanEdit = function () {
		return !$scope.archived;

	};

	// filter cards here, as ng-sortable will not work nicely with html-inline filters
	$scope.filterData = function (order, text) {
		if ($scope.stacks === undefined)
			return;
		angular.copy(StackService.getAll(), $scope.stacks);
		angular.forEach($scope.stacks, function (value, key) {
			var cards = $filter('cardSearchFilter')(value.cards, text);
			cards = $filter('orderBy')(cards, order);
			$scope.stacks[key].cards = cards;
		});
	};

	$scope.loadDefault = function () {
		StackService.fetchAll($scope.id).then(function (data) {
			$scope.statusservice.releaseWaiting();
		}, function (error) {
			$scope.statusservice.setError('Error occured', error);
		});
	};

	$scope.loadArchived = function () {
		StackService.fetchArchived($scope.id).then(function (data) {
			$scope.statusservice.releaseWaiting();
		}, function (error) {
			$scope.statusservice.setError('Error occured', error);
		});
	};

	// Handle initial Loading
	BoardService.fetchOne($scope.id).then(function (data) {
		$scope.statusservice.releaseWaiting();
	}, function (error) {
		$scope.statusservice.setError('Error occured', error);
	});


	BoardService.searchUsers('%25');

	$scope.searchForUser = function (search) {
		if (search == "") {
			search = "%25";
		}
		BoardService.searchUsers(search);
	};

	$scope.newStack = {'boardId': $scope.id};
	$scope.newCard = {};

	// Create a new Stack
	$scope.createStack = function () {
		StackService.create($scope.newStack).then(function (data) {
			$scope.newStack.title = "";
		});
	};

	$scope.createCard = function (stack, title) {
		var newCard = {
			'title': title,
			'stackId': stack,
			'type': 'plain'
		};
		CardService.create(newCard).then(function (data) {
			// FIXME: called here reorders
			$scope.stackservice.addCard(data);
			$scope.newCard.title = "";
		});
	};

	$scope.cardDelete = function (card) {
		CardService.delete(card.id);
		StackService.deleteCard(card);
	};
	$scope.cardArchive = function (card) {
		CardService.archive(card);
		StackService.deleteCard(card);
	};
	$scope.cardUnarchive = function (card) {
		CardService.unarchive(card);
		StackService.deleteCard(card);
	};

	$scope.labelDelete = function (label) {
		LabelService.delete(label.id);
		// remove from board data
		var i = BoardService.getCurrent().labels.indexOf(label);
		BoardService.getCurrent().labels.splice(i, 1);
		// TODO: remove from cards
	};
	$scope.labelCreate = function (label) {
		label.boardId = $scope.id;
		LabelService.create(label);
		BoardService.getCurrent().labels.push(label);
		$scope.status.createLabel = false;
		$scope.newLabel = {};
	};
	$scope.labelUpdate = function (label) {
		label.edit = false;
		LabelService.update(label);
	};

	$scope.aclAdd = function (sharee) {
		sharee.boardId = $scope.id;
		BoardService.addAcl(sharee);
		$scope.status.addSharee = null;
	};
	$scope.aclDelete = function (acl) {
		BoardService.deleteAcl(acl);
	};
	$scope.aclUpdate = function (acl) {
		BoardService.updateAcl(acl);
	};


	// settings for card sorting
	$scope.sortOptions = {
		itemMoved: function (event) {
			event.source.itemScope.modelValue.status = event.dest.sortableScope.$parent.column;
			var order = event.dest.index;
			var card = event.source.itemScope.c;
			var newStack = event.dest.sortableScope.$parent.s.id;
			var oldStack = card.stackId;
			card.stackId = newStack;
			CardService.update(card);
			CardService.reorder(card, order).then(function (data) {
				StackService.addCard(card);
				StackService.reorder(card, order);
				StackService.deleteCard({
					id: card.id,
					stackId: oldStack
				});
			});
		},
		orderChanged: function (event) {
			var order = event.dest.index;
			var card = event.source.itemScope.c;
			var stack = event.dest.sortableScope.$parent.s.id;
			CardService.reorder(card, order).then(function (data) {
				StackService.reorder(card, order);
				$scope.refreshData();
			});
		},
		scrollableContainer: '#board',
		containerPositioning: 'relative',
		containment: '#board',
		// auto scroll on drag
		dragMove: function (itemPosition, containment, eventObj) {
			if (eventObj) {
				var container = $("#board");
				var offset = container.offset();
				var targetX = eventObj.pageX - (offset.left || container.scrollLeft());
				var targetY = eventObj.pageY - (offset.top || container.scrollTop());
				if (targetX < offset.left) {
					container.scrollLeft(container.scrollLeft() - 50);
				} else if (targetX > container.width()) {
					container.scrollLeft(container.scrollLeft() + 50);
				}
				if (targetY < offset.top) {
					container.scrollTop(container.scrollTop() - 50);
				} else if (targetY > container.height()) {
					container.scrollTop(container.scrollTop() + 50);
				}
			}
		}
	};

});
