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
app.controller('BoardController', function ($rootScope, $scope, $stateParams, StatusService, BoardService, StackService, CardService, LabelService, $state, $transitions, $filter, FileService) {

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
	$scope.uploader = FileService.uploader;

	$scope.$watch(function() {
		return $state.current;
	}, function(currentState) {
		if(currentState.name === 'board.detail') {
			CardService.fetchDeleted($scope.id);
			StackService.fetchDeleted($scope.id);
		}
	});

	// workaround for $stateParams changes not being propagated
	$scope.$watch(function() {
		return $state.params;
	}, function (params) {
		$scope.params = params;
	}, true);
	$scope.params = $state.params;

	/**
	 * Check for markdown checkboxes in description to render the counter
	 *
	 * This should probably be moved to the backend at some point
	 *
	 * @param text
	 * @returns array of [finished, total] checkboxes
	 */
	$scope.getCheckboxes = function(text) {
		const regTotal = /\[(X|\s|\_|\-)\]\s(.*)/ig;
		const regFinished = /\[(X|\_|\-)\]\s(.*)/ig;
		return [
			((text || '').match(regFinished) || []).length,
			((text || '').match(regTotal) || []).length
		];
	};

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

	$scope.stackDelete = function (stack) {
		$scope.stackservice.delete(stack.id);
	};

	$scope.stackUndoDelete = function (deletedStack) {
		return StackService.undoDelete(deletedStack);
	};

	$scope.cardDelete = function (card) {
		CardService.delete(card.id).then(function () {
			StackService.removeCard(card);
		});
	};

	$scope.cardUndoDelete = function (deletedCard) {
		var associatedDeletedStack = $scope.stackservice.deleted[deletedCard.stackId];
		if(associatedDeletedStack !== undefined) {
			$scope.cardAndStackUndoDelete(deletedCard, associatedDeletedStack);
		} else {
			$scope._cardUndoDelete(deletedCard);
		}
	};

	$scope.cardAndStackUndoDelete = function(deletedCard, associatedDeletedStack) {
		OC.dialogs.confirm(
			t('deck', 'The associated stack is deleted as well, it will be restored as well.'),
			t('deck', 'Restore associated stack'),
			function(state) {
				if (state) {
					$scope._cardAndStackUndoDelete(deletedCard, associatedDeletedStack);
				}
			}
		);
	}

	$scope._cardAndStackUndoDelete = function(deletedCard, associatedDeletedStack) {
		$scope.stackUndoDelete(associatedDeletedStack).then(function() {
			$scope._cardUndoDelete(deletedCard);
		});
	}

	$scope._cardUndoDelete = function(deletedCard) {
		CardService.undoDelete(deletedCard).then(function() {
			StackService.addCard(deletedCard);
		});
	}

	$scope.cardArchive = function (card) {
		CardService.archive(card);
		StackService.removeCard(card);
	};
	$scope.isCurrentUserAssigned = function (card) {
		if (! CardService.get(card.id).assignedUsers) {
			return false;
		}
		var userList = CardService.get(card.id).assignedUsers.filter(function (obj) {
			return obj.participant.uid === OC.getCurrentUser().uid;
		});
		return userList.length === 1;
	};
	$scope.cardAssignToMe = function (card) {
		CardService.assignUser(card, OC.getCurrentUser().uid)
			.then(
				function() {StackService.updateCard(card);}
			);
		// TODO: remove this jquery call. Fix and use appPopoverMenuUtils instead
		$('.popovermenu').addClass('hidden');
	};
	$scope.cardUnassignFromMe = function (card) {
		CardService.unassignUser(card, OC.getCurrentUser().uid);
		StackService.updateCard(card);
		// TODO: remove this jquery call.Fix and use appPopoverMenuUtils instead
		$('.popovermenu').addClass('hidden');
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

	$scope.attachmentCount = function(card) {
		if (Array.isArray(card.attachments)) {
			return card.attachments.filter((obj) => obj.deletedAt === 0).length;
		}
		return card.attachmentCount;
	};
});
