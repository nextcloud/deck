app.factory('ApiService', function($http, $q){
    var ApiService = function(http, endpoint) {
        this.endpoint = endpoint;
        this.baseUrl = OC.generateUrl('/apps/deck/' + endpoint);
        this.http = http;
        this.q = $q;
        this.data = {};
        this.id = null;
    };

    // TODO: Unify error messages
    ApiService.prototype.fetchAll = function(){
        var deferred = $q.defer();
        var self = this;
        $http.get(this.baseUrl).then(function (response) {
            var objects = response.data;
            objects.forEach(function (obj) {
                self.data[obj.id] = obj;
            });
            deferred.resolve(self.data);
        }, function (error) {
            deferred.reject('Error while ' + self.getName() + '.fetchAll() ');

        });
        return deferred.promise;
    }

    ApiService.prototype.fetchOne = function (id) {

        this.id = id;
        var deferred = $q.defer();

        if(id===undefined) {
            return deferred.promise;
        }

        var self = this;
        $http.get(this.baseUrl + '/' + id).then(function (response) {
            data = response.data;
            self.data[data.id] = response.data;
            deferred.resolve(response.data);

        }, function (error) {
            deferred.reject('Error in ' + self.endpoint + ' fetchAll() ');
        });
        return deferred.promise;
    };

    ApiService.prototype.create = function (entity) {
        var deferred = $q.defer();
        var self = this;
        $http.post(this.baseUrl, entity).then(function (response) {
            self.add(response.data);
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error in ' + self.endpoint + ' create() ');
        });
        return deferred.promise;
    };

    ApiService.prototype.update = function (entity) {
        var deferred = $q.defer();
        var self = this;
        $http.put(this.baseUrl + '/' + entity.id, entity).then(function (response) {
            self.add(response.data);
            deferred.resolve(response.data);
        }, function (error) {
            deferred.reject('Error while update ' + self.endpoint);
        });
        return deferred.promise;

    };

    ApiService.prototype.delete = function (id) {
        var deferred = $q.defer();
        var self = this;

        $http.delete(this.baseUrl + '/' + id).then(function (response) {
            self.remove(id);
            deferred.resolve(response.data);

        }, function (error) {
            deferred.reject('Error while delete ' + self.endpoint);
        });
        return deferred.promise;

    };
    

    // methods for managing data
    ApiService.prototype.clear = function() {
        this.data = {};
    }
    ApiService.prototype.add = function (entity) {
        var element = this.data[entity.id];
        if(element===undefined) {
            this.data[entity.id] = entity;
        } else {
            Object.keys(entity).forEach(function (key) {
                element[key] = entity[key];
                if(element[key]!==null)
                    element[key].status = {};
            });
        }
    };
    ApiService.prototype.remove = function(id) {
        if (this.data[id] !== undefined) {
            delete this.data[id];
        }
    };
    ApiService.prototype.addAll = function (entities) {
        var self = this;
        angular.forEach(entities, function(entity) {
            self.add(entity);
        });
    };

    ApiService.prototype.getCurrent = function () {
        return this.data[this.id];
    }

    ApiService.prototype.getAll = function () {
        return this.data;
    }

    ApiService.prototype.getName = function() {
        var funcNameRegex = /function (.{1,})\(/;
        var results = (funcNameRegex).exec((this).constructor.toString());
        return (results && results.length > 1) ? results[1] : "";
    };

    return ApiService;

});
