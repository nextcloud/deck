app.factory('LabelService', function(ApiService, $http, $q){
    var LabelService = function($http, ep, $q) {
        ApiService.call(this, $http, ep, $q);
    };
    LabelService.prototype = angular.copy(ApiService.prototype);
    service = new LabelService($http, 'labels', $q)
    return service;
});