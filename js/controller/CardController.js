

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

app.controller('CardController', function ($scope, $rootScope, $routeParams, $location, $stateParams, BoardService, CardService, StackService, StatusService) {
    $scope.sidebar = $rootScope.sidebar;
    $scope.status = {};

    $scope.cardservice = CardService;
    $scope.cardId = $stateParams.cardId;

    $scope.statusservice = StatusService.getInstance();
    $scope.boardservice = BoardService;

    $scope.statusservice.retainWaiting();

    CardService.fetchOne($scope.cardId).then(function(data) {
        $scope.statusservice.releaseWaiting();
        $scope.archived = CardService.getCurrent().archived;
    }, function(error) {
    });

    $scope.cardRenameShow = function() {
        if($scope.archived)
            return false;
        else {
            $scope.status.cardRename=true;
        }
    };
    $scope.cardEditDescriptionShow = function() {
        if($scope.archived)
            return false;
        else {
            $scope.status.cardEditDescription=true;
        }
    };
    // handle rename to update information on the board as well
    $scope.cardRename = function(card) {
        CardService.rename(card).then(function(data) {
            StackService.updateCard(card);
            $scope.status.renameCard = false;
        });
    };
    $scope.cardUpdate = function(card) {
        CardService.update(CardService.getCurrent()).then(function(data) {
            $scope.status.cardEditDescription = false;
            $('#card-description .save-indicator').fadeIn(500).fadeOut(1000);
        });
    }

    $scope.labelAssign = function(element, model) {
        CardService.assignLabel($scope.cardId, element.id);
        var card = CardService.getCurrent();
        StackService.updateCard(card);
    }
    
    $scope.labelRemove = function(element, model) {
        CardService.removeLabel($scope.cardId, element.id)
    }

});
