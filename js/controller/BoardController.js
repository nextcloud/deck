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
/* global oc_defaults OC */
app.controller('BoardController', function ($rootScope, $scope, $timeout, $stateParams, StatusService, BoardService, StackService, CardService, LabelService, $state, $transitions, $filter) {

	$scope.sidebar = $rootScope.sidebar;

	$scope.id = $stateParams.boardId;
	$scope.status = {
		addCard: [],
	};
	$scope.newLabel = {};

	$scope.OC = OC;
	$scope.stackservice = StackService;
	$scope.boardservice = BoardService;
	$scope.cardservice = CardService;
	$scope.statusservice = StatusService.getInstance();
	$scope.labelservice = LabelService;
	$scope.defaultColors = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];
	$scope.board = BoardService.getCurrent();

	// workaround for $stateParams changes not being propagated
	$scope.$watch(function() {
		return $state.params;
	}, function (params) {
		$scope.params = params;
	}, true);
	$scope.params = $state;


	$scope.search = function (searchText) {
		$scope.searchText = searchText;
		$scope.refreshData();
	};

	$scope.$watch(function () {
		if (typeof BoardService.getCurrent() !== 'undefined') {
			return BoardService.getCurrent().title;
		} else {
			return null;
		}
	}, function () {
		$scope.setPageTitle();
	});
	$scope.setPageTitle = function () {
		if (BoardService.getCurrent()) {
			document.title = BoardService.getCurrent().title + ' | Deck - ' + oc_defaults.name;
		} else {
			document.title = 'Deck - ' + oc_defaults.name;
		}
	};

	$scope.statusservice.retainWaiting();
	$scope.statusservice.retainWaiting();

	// handle filter parameter for switching between archived/unarchived cards
	$scope.switchFilter = function (filter) {
		$state.go('.', {filter: filter});
	};
	$scope.$watch(function() {
		return $scope.params.filter;
	}, function (filter) {
		if (filter === 'archive') {
			$scope.loadArchived();
		} else {
			$scope.loadDefault();
		}
	});

	$scope.stacksData = StackService;
	$scope.stacks = [];
	$scope.$watch('stacksData', function () {
		$scope.refreshData();
	}, true);
	$scope.refreshData = function () {
		if ($scope.params.filter === 'archive') {
			$scope.filterData('-lastModified', $scope.searchText);
		} else {
			$scope.filterData('order', $scope.searchText);
		}
	};
	$scope.checkCanEdit = function () {
		return !BoardService.getCurrent().archived;
	};

	// filter cards here, as ng-sortable will not work nicely with html-inline filters
	$scope.filterData = function (order, text) {
		if ($scope.stacks === undefined) {
			return;
		}
		angular.copy(StackService.getData(), $scope.stacks);
		$scope.stacks = $filter('orderBy')($scope.stacks, 'order');
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
		$scope.setPageTitle();
	}, function (error) {
		$scope.statusservice.setError('Error occured', error);
	});

	$scope.searchForUser = function (search) {
		BoardService.searchUsers(search);
	};

	$scope.newStack = {'boardId': $scope.id};
	$scope.newCard = {};

	// Create a new Stack
	$scope.createStack = function () {
		StackService.create($scope.newStack).then(function (data) {
			$scope.newStack.title = '';
		});
	};

	$scope.createCard = function (stack, title) {
		var newCard = {
			'title': title,
			'stackId': stack,
			'type': 'plain'
		};
		CardService.create(newCard).then(function (data) {
			$scope.stackservice.addCard(data);
			$scope.newCard.title = '';
		});
	};

	$scope.cardDelete = function (card) {
		OC.dialogs.confirm(t('deck', 'Are you sure you want to delete this card with all of its data?'), t('deck', 'Delete'), function(state) {
			if (!state) {
				return;
			}
			CardService.delete(card.id).then(function () {
				StackService.removeCard(card);
			});
		});
	};
	$scope.cardArchive = function (card) {
		CardService.archive(card);
		StackService.removeCard(card);
	};
	$scope.cardUnarchive = function (card) {
		CardService.unarchive(card);
		StackService.removeCard(card);
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
		LabelService.create(label).then(function (data) {
			$scope.newStack.title = '';
			BoardService.getCurrent().labels.push(data);
			$scope.status.createLabel = false;
			$scope.newLabel = {};
		});
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
		BoardService.deleteAcl(acl).then(function(data) {
			$scope.loadDefault();
			$scope.refreshData();
		});
	};
	$scope.aclUpdate = function (acl) {
		BoardService.updateAcl(acl);
	};

	$scope.aclTypeString = function (acl) {
		if (typeof acl === 'undefined') {
			return '';
		}
		switch (acl.type) {
			case OC.Share.SHARE_TYPE_USER:
				return 'user';
			case OC.Share.SHARE_TYPE_GROUP:
				return 'group';
			default:
				return '';
		}
	};

	// settings for card sorting
	$scope.sortOptions = {
		id: 'card',
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
				StackService.reorderCard(card, order);
				StackService.removeCard({
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
				StackService.reorderCard(card, order);
				$scope.refreshData();
			});
		},
		scrollableContainer: '#innerBoard',
		containerPositioning: 'relative',
		containment: '#innerBoard',
		longTouch: true,
		// auto scroll on drag
		dragMove: function (itemPosition, containment, eventObj) {
			if (eventObj) {
				var container = $('#board');
				var offset = container.offset();
				var targetX = eventObj.pageX - (offset.left || container.scrollLeft());
				var targetY = eventObj.pageY - (offset.top || container.scrollTop());
				if (targetX < offset.left) {
					container.scrollLeft(container.scrollLeft() - 25);
				} else if (targetX > container.width()) {
					container.scrollLeft(container.scrollLeft() + 25);
				}
				if (targetY < offset.top) {
					container.scrollTop(container.scrollTop() - 25);
				} else if (targetY > container.height()) {
					container.scrollTop(container.scrollTop() + 25);
				}
			}
		},
		accept: function (sourceItemHandleScope, destSortableScope, destItemScope) {
			return sourceItemHandleScope.sortableScope.options.id === 'card';
		}
	};

	$scope.sortOptionsStack = {
		id: 'stack',
		orderChanged: function (event) {
			var order = event.dest.index;
			var stack = event.source.itemScope.s;
			StackService.reorder(stack, order).then(function (data) {
				$scope.refreshData();
			});
		},
		scrollableContainer: '#board',
		containerPositioning: 'relative',
		containment: '#innerBoard',
		dragMove: function (itemPosition, containment, eventObj) {
			if (eventObj) {
				var container = $('#board');
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
		},
		accept: function (sourceItemHandleScope, destSortableScope, destItemScope) {
			return sourceItemHandleScope.sortableScope.options.id === 'stack';
		}
	};

	$scope.labelStyle = function (color) {
		return {
			'background-color': '#' + color,
			'color': $filter('textColorFilter')(color)
		};
	};

	// make sure esc is also triggered on inputs

	/* keycode: A archive current card */
	$scope.$on('keypress:a', function() {
		if ($scope.selectedCard()) {
			if ($scope.params.filter === 'archived') {
				$scope.cardUnarchive($scope.selectedCard());
			} else {
				$scope.cardArchive($scope.selectedCard());
			}
			$scope.resetSelectedCard();
		}
	});
	$scope.$on('keypress:c', function(e) {
		e.preventDefault();
		// use timeout to prevent input being visible when keypress event is handed to the browser
		$timeout(function() {
			$scope.status.addCard[$scope.selectedCard().stackId]=true;
			$scope.$apply();
		});
		return false;
	});
	/* keycode: Enter - open current card */
	$scope.$on('keypress:Enter', function() {
		$state.go('board.card', {boardId: $scope.id, cardId: $scope.selectedCard().id});
	});

	const Arrow = {
		KEY_DOWN: 'ArrowDown',
		KEY_UP: 'ArrowUp',
		KEY_LEFT: 'ArrowLeft',
		KEY_RIGHT: 'ArrowRight',
	};

	$scope.resetSelectedCard = function() {
		$scope.status.selectedCard = null;
	};
	$scope.selectedCard = function() {
		if (!$scope.status.selectedCard) {
			$scope.status.selectedCard = $scope.status.hoverCard;
		}
		if (!$scope.status.selectedCard) {
			$scope.status.selectedCard = $scope.stacks[0].cards[0];
			$scope.$apply();
		}
		return $scope.status.selectedCard;
	};
	$scope.selectCard = function(key) {
		if (!$scope.status.selectedCard) {
			$scope.status.selectedCard = $scope.status.hoverCard;
		}
		if (!$scope.status.selectedCard) {
			$scope.status.selectedCard = $scope.stacks[0].cards[0];
			$scope.$apply();
			return;
		}
		let stackId = $scope.status.selectedCard.stackId;
		let cardId = $scope.status.selectedCard.id;
		let currentStack = $filter('filter')($scope.stacks, {id: stackId}, true)[0];
		let currentCard = $filter('filter')(currentStack.cards, {id: cardId}, true)[0];
		let currentCardIndex = currentStack.cards.map((e) => e.id).indexOf(currentCard.id);
		let currentStackIndex = $scope.stacks.map((e) => e.id).indexOf(currentStack.id);

		let nextCard = null;
		// TODO: handle empty stacks
		switch (key) {
			case Arrow.KEY_DOWN:
				currentCardIndex++;
				break;
			case Arrow.KEY_UP:
				currentCardIndex--;
				break;
			case Arrow.KEY_LEFT:
				currentStackIndex--;
				break;
			case Arrow.KEY_RIGHT:
				currentStackIndex++;
				break;
		}
		currentStackIndex = (currentStackIndex < 0) ? 0 : currentStackIndex;
		currentStackIndex = (currentStackIndex >= $scope.stacks.length) ? $scope.stacks.length-1 : currentStackIndex;
		currentCardIndex = (currentCardIndex < 0) ? 0 : currentCardIndex;
		currentCardIndex = (currentCardIndex >= $scope.stacks[currentStackIndex].cards.length) ? $scope.stacks[currentStackIndex].cards.length-1 : currentCardIndex;
		nextCard = $scope.stacks[currentStackIndex].cards[currentCardIndex];
		if (nextCard !== null) {
			$scope.status.selectedCard = nextCard;
			console.log($scope.status.selectedCard);
		}
		$scope.$apply();
	};
	/* keycode: Arrow - open current card */
	$scope.$on('keydown:' + Arrow.KEY_DOWN, function() {
		$scope.selectCard( Arrow.KEY_DOWN);
	});
	$scope.$on('keydown:' + Arrow.KEY_UP, function() {
		$scope.selectCard( Arrow.KEY_UP);
	});
	$scope.$on('keydown:' + Arrow.KEY_LEFT, function() {
		$scope.selectCard( Arrow.KEY_LEFT);
	});
	$scope.$on('keydown:' + Arrow.KEY_RIGHT, function() {
		$scope.selectCard( Arrow.KEY_RIGHT);
	});
	$scope.$on('keydown:Escape', function() {
		$scope.status.addCard[$scope.selectedCard().stackId] = false;
		$scope.status.selectedCard = null;
	});
});
