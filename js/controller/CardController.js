

app.controller('CardController', function ($scope, $rootScope, $routeParams, $location, $stateParams, CardService) {
    $scope.sidebar = $rootScope.sidebar;

    $scope.cardservice = CardService;
    $scope.cardId = $stateParams.cardId;

    CardService.fetchOne($scope.cardId).then(function(data) {
        console.log(data);
    }, function(error) {
    });




    /*var menu = $('#app-content');
     menu.click(function(event){
     $scope.location.path('/board/'+$scope.boardId);
     $scope.$apply();

     });*/
});
