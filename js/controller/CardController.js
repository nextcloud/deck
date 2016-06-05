

app.controller('CardController', function ($scope, $rootScope, $routeParams, $location, $stateParams) {
    $scope.sidebar = $rootScope.sidebar;

    $scope.location = $location;
    $scope.card = {'id': 1, 'title': 'We should implement all the useful things, that a kanban like project managemnt system needs for having success', 'description': 'Non et quibusdam officiis expedita excepturi. Tenetur ea et dignissimos qui. Rerum quis commodi aperiam amet dolorum suscipit asperiores. Enim dolorem ea nisi voluptate. \
                Consequatur enim iste dolore autem est unde voluptatum. Aut sit et iure. Suscipit deserunt nisi repellat in officiis alias. Nihil beatae ea ut laudantium at.\
                Doloribus nihil ipsa consequatur laudantium qui enim eveniet quo. Voluptatum tenetur sunt quis sint aliquam et molestias. Quae voluptatem tempora qui eaque qui esse possimus magni. Animi dolorem maiores iste.\
                Totam ut tempora officiis ipsam dolorem modi. Dolores hic aut itaque. Earum in est voluptas voluptatum. Cumque pariatur qui omnis placeat. Eius sed sunt corrupti dolorem quo.'};
    $scope.cardId = $stateParams.cardId;

    console.log($stateParams);



    /*var menu = $('#app-content');
     menu.click(function(event){
     $scope.location.path('/board/'+$scope.boardId);
     $scope.$apply();

     });*/
});
