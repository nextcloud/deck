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
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while update ' + self.endpoint);
        });
        return deferred.promise;
    };

    BoardService.prototype.addAcl = function(acl) {
        var board = this.getCurrent();
        var deferred = $q.defer();
        var self = this;
        var _acl = acl;
        $http.post(this.baseUrl + '/acl', _acl).then(function (response) {
            if(!board.acl) {
                board.acl = {};
            }
            board.acl[response.data.id] = response.data;
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error creating ACL ' + _acl);
        });
        acl = null;
        return deferred.promise;
    };

    BoardService.prototype.deleteAcl = function(id) {
        var board = this.getCurrent();
        var deferred = $q.defer();
        var self = this;
        $http.delete(this.baseUrl + '/acl/' + id).then(function (response) {
            delete board.acl[response.data.id];
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error deleting ACL ' + id);
        });
        acl = null;
        return deferred.promise;
    };

    BoardService.prototype.updateAcl = function(acl) {
        var board = this.getCurrent();
        var deferred = $q.defer();
        var self = this;
        var _acl = acl;
        $http.put(this.baseUrl + '/acl', _acl).then(function (response) {
            board.acl[_acl.id] = response.data;
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error updating ACL ' + _acl);
        });
        acl = null;
        return deferred.promise;
    };

    service = new BoardService($http, 'boards', $q)
    return service;
    
});