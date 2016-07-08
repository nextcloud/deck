app.factory('BoardService', function(ApiService, $http, $q){
    var BoardService = function($http, ep, $q) {
        ApiService.call(this, $http, ep, $q);
    };
    BoardService.prototype = angular.copy(ApiService.prototype);

    BoardService.prototype.searchUsers = function() {
        var url = OC.generateUrl('/apps/deck/share/search/%');
        var deferred = $q.defer();
        var self = this;
        this.sharees = [];
        $http.get(url).then(function (response) {
            self.sharees = response.data;
            console.log(self.sharees);
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while update ' + self.endpoint);
        });
        return deferred.promise;
    }

    BoardService.prototype.addSharee = function(sharee) {
        var board = this.getCurrent();
        board.acl.push(sharee);
        var deferred = $q.defer();
        var self = this;
        $http.post(this.baseUrl + '/sharee', sharee).then(function (response) {
            console.log("Add sharee " + response);
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while insert ' + self.endpoint);
        });
        sharee = null;
        return deferred.promise;
    }

    service = new BoardService($http, 'boards', $q)
    return service;
    
});