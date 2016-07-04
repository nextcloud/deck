

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

        console.log(data);
    }, function(error) {
    });


    // handle rename to update information on the board as well
    $scope.renameCard = function(card) {
        CardService.rename(card).then(function(data) {
            StackService.updateCard(card);
            $scope.status.renameCard = false;
        });
    };

    $scope.updateCard = function(card) {
        CardService.update(CardService.getCurrent());
        $scope.status.description = false;
    }

    $scope.editDescription = function() {
        $scope.status.description = true;
    }

    $scope.labelAssign = function(element, model) {
        CardService.assignLabel($scope.cardId, element.id);
        var card = CardService.getCurrent();
        StackService.updateCard(card);
    }
    $scope.labelRemove = function(element, model) {
        CardService.removeLabel($scope.cardId, element.id)
    }

    /*var menu = $('#app-content');
     menu.click(function(event){
     $scope.location.path('/board/'+$scope.boardId);
     $scope.$apply();

     });*/
});
