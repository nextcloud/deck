app.factory('boardFactory', function($http, stackFactory){
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