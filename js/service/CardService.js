app.factory('CardService', function(ApiService, $http, $q){
    var CardService = function($http, ep, $q) {
        ApiService.call(this, $http, ep, $q);
    };
    CardService.prototype = angular.copy(ApiService.prototype);

    CardService.prototype.reorder = function(card, order) {
        var deferred = $q.defer();
        var self = this;
        $http.put(this.baseUrl + '/reorder', {cardId: card.id, order: order, stackId: card.stackId}).then(function (response) {
            card.order = order;
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while update ' + self.endpoint);
        });
        return deferred.promise;
    }

    CardService.prototype.rename = function(card) {
        var deferred = $q.defer();
        var self = this;
        $http.put(this.baseUrl + '/rename', {cardId: card.id, title: card.title}).then(function (response) {
            self.data[card.id].title = card.title;
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while renaming ' + self.endpoint);
        });
        return deferred.promise;
    }

    CardService.prototype.assignLabel = function(card, label) {
        //['name' => 'card#assignLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'POST'],
        var url = this.baseUrl + '/' + card + '/label/' + label;
        var deferred = $q.defer();
        var self = this;
        $http.post(url).then(function (response) {
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while update ' + self.endpoint);
        });
        return deferred.promise;
    }
    CardService.prototype.removeLabel = function(card, label) {
       // ['name' => 'card#removeLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'DELETE'],
        var url = this.baseUrl + '/' + card + '/label/' + label;
        var deferred = $q.defer();
        var self = this;
        $http.delete(url).then(function (response) {
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while update ' + self.endpoint);
        });
        return deferred.promise;
    }

    service = new CardService($http, 'cards', $q)
    return service;
});