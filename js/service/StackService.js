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
        this.data[entity.stackId].cards.push(entity);
    }
    StackService.prototype.updateCard = function(entity) {
        var self = this;
        var cards = this.data[entity.stackId].cards;
        for(var i=0;i<cards.length;i++) {
            if(cards[i].id == entity.id) {
                cards[i] = entity;
            }
        }
    }
    StackService.prototype.deleteCard = function(entity) {
        var self = this;
        var cards = this.data[entity.stackId].cards;
        for(var i=0;i<cards.length;i++) {
            if(cards[i].id == entity.id) {
                cards.splice(i, 1);
            }
        }
    }
    service = new StackService($http, 'stacks', $q);
    return service;
});

