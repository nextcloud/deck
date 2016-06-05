app.factory('ApiService', function($http){
    var ApiService = function(http, BASEURL,endpoint) {
        this.endpoint = endpoint;
        this.baseUrl = OC.generateUrl('/apps/deck/' + endpoint);
        this.http = http;
        this.hashMap = {};
        this.values = [];
    };

    ApiService.prototype.getAll = function(){
        return $http.get(baseUrl);
    }

    ApiService.prototype.getOne = function (id) {
        return $http.get(baseUrl + '/' + id);
    };

    ApiService.prototype.create = function (entity) {
        return $http.post(baseUrl, entity);
    };

    ApiService.prototype.update = function (entity) {
        return $http.put(baseUrl, entity)
    };

    ApiService.prototype.delete = function (id) {
        return $http.delete(baseUrl + '/' + id);
    };

    return ApiService;
});