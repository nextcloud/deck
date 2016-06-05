app.factory('ApiService', function($http){
    var ApiService = function(http, endpoint) {
        this.endpoint = endpoint;
        this.baseUrl = OC.generateUrl('/apps/deck/' + endpoint);
        this.http = http;
        this.hashMap = {};
        this.values = [];
        this.once = null;
    };

    ApiService.prototype.getAll = function(){
        return $http.get(this.baseUrl);
    }

    ApiService.prototype.getOne = function (id) {
        self = this;
        $http.get(this.baseUrl + '/' + id).then(function (response) {
            self.once = response.data;
            return response.data;
        }, function (error) {
        });
        return this.once;

    };

    ApiService.prototype.create = function (entity) {
        return $http.post(this.baseUrl, entity);
    };

    ApiService.prototype.update = function (entity) {
        return $http.put(this.baseUrl, entity)
    };

    ApiService.prototype.delete = function (id) {
        return $http.delete(this.baseUrl + '/' + id);
    };

    return ApiService;
});

app.factory('stackFactory', function($http){
    var service = {};
    var baseUrl = OC.generateUrl('/apps/deck/stacks');

    service.getStacks = function(boardId){
        return $http.get(baseUrl + '/' + boardId);
    }

    service.getStack = function (id) {
        return $http.get(baseUrl + '/' + id);
    };

    service.createStack = function (stack) {
        return $http.post(baseUrl, stack);
    };

    service.updateStack = function (stack) {
        return $http.put(baseUrl, stack)
    };

    service.deleteStack = function (id) {
        return $http.delete(baseUrl + '/' + id);
    };

    return service;
});

app.factory('StackService', function(ApiService, $http){
    var StackService = function($http, ep) {
        ApiService.call(this, $http, ep);
    };
    StackService.prototype = ApiService.prototype;
    StackService.prototype.getAll = function(boardId) {
        return $http.get(this.baseUrl + '/' + boardId);
    }
    service = new StackService($http, 'stacks')
    return service;
});