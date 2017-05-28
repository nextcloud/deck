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

/* global app angular */

app.controller('ListController', function ($scope, $location, $filter, BoardService, $element, $timeout, $stateParams, $state) {
	$scope.boards = [];
	$scope.newBoard = {};
	$scope.status = {
		deleteUndo: [],
		filter: $stateParams.filter ? $stateParams.filter : '',
		sidebar: false
	};
	$scope.colors = ['0082c9', '00c9c6','00c906', 'c92b00', 'F1DB50', '7C31CC', '3A3B3D', 'CACBCD'];
	$scope.boardservice = BoardService;
	$scope.newBoard.color = $scope.colors[0];

	// FIXME: not nice, but we want to load this only once
	if($element.attr('id') === 'app-navigation') {
		BoardService.fetchAll().then(function(data) {
			$scope.filterData();
		}, function (error) {
			// TODO: show error when loading fails
		});
	}

	$scope.filterData = function () {
		angular.copy($scope.boardservice.getData(), $scope.boardservice.sorted);
		angular.copy($scope.boardservice.sorted, $scope.boardservice.sidebar);
		$scope.boardservice.sidebar = $filter('orderBy')($scope.boardservice.sidebar, 'title');
		$scope.boardservice.sidebar = $filter('cardFilter')($scope.boardservice.sidebar, {archived: false});

		if ($scope.status.filter === 'archived') {
			var filter = {};
			filter[$scope.status.filter] = true;
			$scope.boardservice.sorted = $filter('cardFilter')($scope.boardservice.sorted, filter);
		} else if ($scope.status.filter === 'shared') {
			$scope.boardservice.sorted = $filter('cardFilter')($scope.boardservice.sorted, {archived: false});
			$scope.boardservice.sorted = $filter('boardFilterAcl')($scope.boardservice.sorted);
		} else {
			$scope.boardservice.sorted = $filter('cardFilter')($scope.boardservice.sorted, {archived: false});
		}
		$scope.boardservice.sorted = $filter('orderBy')($scope.boardservice.sorted, ['deletedAt', 'title']);
	};

	$scope.$watchCollection(function(){
		return $state.params;
	}, function(){
		$scope.status.filter = $state.params.filter;
		$scope.filterData();
	});


	$scope.selectColor = function(color) {
		$scope.newBoard.color = color;
	};

	$scope.gotoBoard = function(board) {
		if(board.deletedAt > 0) {
			return false;
		}
		return $state.go('board', {boardId: board.id});
	};

	$scope.boardCreate = function() {
		if(!$scope.newBoard.title || !$scope.newBoard.color) {
			$scope.status.addBoard=false;
			return;
		}
		BoardService.create($scope.newBoard)
			.then(function (response) {
				$scope.newBoard = {};
				$scope.newBoard.color = $scope.colors[0];
				$scope.status.addBoard=false;
				$scope.filterData();
			}, function(error) {
				$scope.status.createBoard = 'Unable to insert board: ' + error.message;
			});
	};

	$scope.boardUpdate = function(board) {
		BoardService.update(board).then(function(data) {
			$scope.filterData();
		});
		board.status.edit = false;
	};

	$scope.boardArchive = function (board) {
		board.archived = true;
		BoardService.update(board).then(function(data) {
			$scope.filterData();
		});
	};

	$scope.boardUnarchive = function (board) {
		board.archived = false;
		BoardService.update(board).then(function(data) {
			$scope.filterData();
		});
	};

	$scope.boardDelete = function(board) {
		BoardService.delete(board.id).then(function (data) {
			$scope.filterData();
		});
	};

	$scope.boardDeleteUndo = function (board) {
		BoardService.deleteUndo(board.id).then(function (data) {
			$scope.filterData();
		});
	};

});

