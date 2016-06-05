
app.controller('AppController', function ($scope, $location, $http, $route, $log, $rootScope, $stateParams) {
    $rootScope.sidebar = {
        show: false
    };
    $scope.sidebar = $rootScope.sidebar;
});