
app.controller('ListController', function ($scope, $location, boardFactory, StackService) {
    $scope.boards = null;
    $scope.newBoard = {};
    $scope.status = {};
    $scope.colors = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];
    $scope.stackservice = StackService;

    $scope.getBoards = function() {
        boardFactory.getBoards()
            .then(function (response) {
                $scope.boards = response.data;
                for (var i = 0; i < $scope.boards.length; i++) {
                    $scope.boards[i].status = {
                        'edit': false,
                    }
                }
            }, function (error) {
                $scope.status.getBoards = 'Unable to load customer data: ' + error.message;
            });
    }

    $scope.createBoard = function () {
        boardFactory.createBoard($scope.newBoard)
            .then(function (response) {
                $scope.boards.push(response.data);
                $scope.newBoard = {};
                $scope.status.addBoard=false;
            }, function(error) {
                $scope.status.createBoard = 'Unable to insert board: ' + error.message;
            });
    };

    $scope.updateBoard = function(board) {
        boardFactory.updateBoard(board)
            .then(function (response) {
                board = response.data;
            }, function(error) {
                $scope.status.createBoard = 'Unable to insert board: ' + error.message;
            });
        board.status.edit = false;
        $scope.$apply();
    };

    $scope.selectColor = function(color) {
        $scope.newBoard.color = color;
    };

    $scope.deleteBoard = function (index) {
        var board = $scope.boards[index];
        boardFactory.deleteBoard(board.id)
            .then(function (response) {
                $scope.status.deleteBoard = 'Deleted Board';
                $scope.boards.splice( index, 1 );

            }, function(error) {
                $scope.status.deleteBoard = 'Unable to insert board: ' + error.message;
            });
    };
    $scope.getBoards();


});

