app.factory('StackService', function(ApiService, $http, $q){
    var StackService = function($http, ep, $q) {
        ApiService.call(this, $http, ep, $q);
    };
    StackService.prototype = angular.copy(ApiService.prototype);
    StackService.prototype.fetchAll = function(boardId) {
        var deferred = $q.defer();
        var self=this;
        $http.get(this.baseUrl +'/'+boardId).then(function (response) {
            self.addAll(response.data);
            deferred.resolve(self.data);
        }, function (error) {
            deferred.reject('Error while loading stacks');
        });
        return deferred.promise;

    }

    StackService.prototype.addCard = function(entity) {
        console.log(this.data[entity.stackId]);
        this.data[entity.stackId].cards.push(entity);
    }
    service = new StackService($http, 'stacks', $q)
    return service;
});

