app.factory('boardFactory', function($http){
    var service = {};
    var baseUrl = OC.generateUrl('/apps/deck/boards');

    service.getBoards = function(){
        return $http.get(baseUrl);
    }

    service.getBoard = function (id) {
        board = $http.get(baseUrl + '/' + id);
        return board;
    };

    service.createBoard = function (board) {

        return $http.post(baseUrl, board);
    };

    service.updateBoard = function (board) {
        return $http.put(baseUrl, board)
    };

    service.deleteBoard = function (id) {
        return $http.delete(baseUrl + '/' + id);
    };

    return service;
});

app.factory('BoardService', function(ApiService, $http, $q){
    var BoardService = function($http, ep, $q) {
        ApiService.call(this, $http, ep, $q);
    };
    BoardService.prototype = angular.copy(ApiService.prototype);
    service = new BoardService($http, 'boards', $q)
    return service;
});