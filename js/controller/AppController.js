
app.controller('AppController', function ($scope, $location, $http, $route, $log, $rootScope, $stateParams) {
    $rootScope.sidebar = {
        show: false
    };
    $scope.sidebar = $rootScope.sidebar;

    $scope.search = function (value) {
        if (value === '') {
            $location.search('search', null);
        } else {
            $location.search('search', value);
        }
        $scope.searchText = value;
    };
    
    $rootScope.searchText = $location.search().search;

});