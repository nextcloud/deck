
var app = angular.module('Deck', ['ngRoute', 'ngSanitize', 'ui.router', 'as.sortable']);

app.config(function ($provide, $routeProvider, $interpolateProvider, $httpProvider, $urlRouterProvider, $stateProvider) {
    'use strict';
    $httpProvider.defaults.headers.common.requesttoken = oc_requesttoken;

    $urlRouterProvider.otherwise("/");

    $stateProvider
        .state('list', {
            url: "/",
            templateUrl: "/boardlist.mainView.html",
            controller: 'ListController',
        })
        .state('board', {
            url: "/board/:boardId",
            templateUrl: "/board.html",
            controller: 'BoardController'
        })
        .state('board.card', {
            url: "/card/:cardId",
            views: {
                "sidebarView": {
                    templateUrl: "/card.sidebarView.html",
                    controller: 'CardController'
                }
            }
        })
        .state('board.settings', {})
        .state('board.sharing', {});
});

// OwnCloud Click Handling
// https://doc.owncloud.org/server/8.0/developer_manual/app/css.html
app.directive('appNavigationEntryUtils', function () {
    'use strict';
    return {
        restrict: 'C',
        link: function (scope, elm) {

            var menu = elm.siblings('.app-navigation-entry-menu');
            var button = $(elm)
                .find('.app-navigation-entry-utils-menu-button button');

            button.click(function () {
                menu.toggleClass('open');
            });
            scope.$on('documentClicked', function (scope, event) {
                if (event.target !== button[0]) {
                    menu.removeClass('open');
                }
            });
        }
    };
});

app.directive('autofocusOnInsert', function () {
    'use strict';
    return function (scope, elm) {
        elm.focus();
    };
});

app.run(function ($document, $rootScope, $transitions) {
    'use strict';
    $document.click(function (event) {
        $rootScope.$broadcast('documentClicked', event);
    });
    $transitions.onEnter({to: 'board.card'}, function ($state, $transition$) {
        $rootScope.sidebar.show = true;
    });
    $transitions.onEnter({to: 'board'}, function ($state) {
        $rootScope.sidebar.show = false;
    });
    $transitions.onExit({from: 'board.card'}, function ($state) {
        $rootScope.sidebar.show = false;
    });
});

app.controller('AppController', function ($scope, $location, $http, $route, $log, $rootScope, $stateParams) {
    $rootScope.sidebar = {
        show: false
    };
    $scope.sidebar = $rootScope.sidebar;
});

app.controller('BoardController', function ($rootScope, $scope, $location, $http, $route, $stateParams, BoardService, StackService) {

  
  $scope.sidebar = $rootScope.sidebar;
  $scope.id = $stateParams.boardId;

  $scope.stackservice = StackService;
  $scope.boardservice = BoardService;

  // fetch data
  StackService.clear();

  StackService.fetchAll($scope.id).then(function(data) {
    console.log($scope.stackservice.data)
    $scope.releaseWaiting();
  }, function(error) {
    $scope.setError('Error occured', error);
  });

  BoardService.fetchOne($scope.id).then(function(data) {
    $scope.releaseWaiting();
  }, function(error) {
    $scope.setError('Error occured', error);
  });

  $scope.newStack = { 'boardId': $scope.id};
  $scope.newCard = {};


  // Status Helper
  $scope.status = {
    'active': true,
    'icon': 'loading',
    'title': 'Bitte warten',
    'text': 'Es dauert noch einen kleinen Moment',
    'counter': 2,
  };

  $scope.setStatus = function($icon, $title, $text='') {
    $scope.status.active = true;
    $scope.status.icon = $icon;
    $scope.status.title = $title;
    $scope.status.text = $text;
  }

  $scope.setError = function($title, $text) {
    $scope.status.active = true;
    $scope.status.icon = 'error';
    $scope.status.title = $title;
    $scope.status.text = $text;
    $scope.status.counter = 0;
  }

  $scope.releaseWaiting = function() {
    if($scope.status.counter>0)
        $scope.status.counter--;
    if($scope.status.counter==0) {
      $scope.status = {
        'active': false
      }
    }
  }

  $scope.unsetStatus = function() {
    $scope.status = {
      'active': false
    }
  }

  // Create a new Stack
  $scope.createStack = function () {
    StackService.create($scope.newStack).then(function (data) {
      $scope.newStack.title="";
    });
  };

  // Lighten Color of the board for background usage
  $scope.rgblight = function (hex) {
      var result = /^([A-Fa-f\d]{2})([A-Fa-f\d]{2})([A-Fa-f\d]{2})$/i.exec(hex);
      var color = result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
      } : null;
    if(result !== null) {
      var rgba = "rgba(" + color.r + "," + color.g + "," + color.b + ",0.7)";
      return rgba;
    } else {
        return "#"+hex;
    }
  };


  // settings for card sorting
  $scope.sortOptions = {
    itemMoved: function (event) {
      event.source.itemScope.modelValue.status = event.dest.sortableScope.$parent.column;
      console.log(event.dest.sortableScope.$parent);
    },
    orderChanged: function (event) {
    },
    scrollableContainer: '#board',
    containerPositioning: 'relative',
    containment: '#board',
    // auto scroll on drag
    dragMove: function (itemPosition, containment, eventObj) {
      if (eventObj) {
        var container = $("#board");
        var offset = container.offset();
        targetX = eventObj.pageX - (offset.left || container.scrollLeft());
        targetY = eventObj.pageY - (offset.top || container.scrollTop());
        if (targetX < offset.left) {
          container.scrollLeft(container.scrollLeft() - 50);
        } else if (targetX > container.width()) {
          container.scrollLeft(container.scrollLeft() + 50);
        }
        if (targetY < offset.top) {
          container.scrollTop(container.scrollTop() - 50);
        } else if (targetY > container.height()) {
          container.scrollTop(container.scrollTop() + 50);
        }
      }
    }
  };

});



app.controller('CardController', function ($scope, $rootScope, $routeParams, $location, $stateParams) {
    $scope.sidebar = $rootScope.sidebar;

    $scope.location = $location;
    $scope.card = {'id': 1, 'title': 'We should implement all the useful things, that a kanban like project managemnt system needs for having success', 'description': 'Non et quibusdam officiis expedita excepturi. Tenetur ea et dignissimos qui. Rerum quis commodi aperiam amet dolorum suscipit asperiores. Enim dolorem ea nisi voluptate. \
                Consequatur enim iste dolore autem est unde voluptatum. Aut sit et iure. Suscipit deserunt nisi repellat in officiis alias. Nihil beatae ea ut laudantium at.\
                Doloribus nihil ipsa consequatur laudantium qui enim eveniet quo. Voluptatum tenetur sunt quis sint aliquam et molestias. Quae voluptatem tempora qui eaque qui esse possimus magni. Animi dolorem maiores iste.\
                Totam ut tempora officiis ipsam dolorem modi. Dolores hic aut itaque. Earum in est voluptas voluptatum. Cumque pariatur qui omnis placeat. Eius sed sunt corrupti dolorem quo.'};
    $scope.cardId = $stateParams.cardId;

    console.log($stateParams);



    /*var menu = $('#app-content');
     menu.click(function(event){
     $scope.location.path('/board/'+$scope.boardId);
     $scope.$apply();

     });*/
});


app.controller('ListController', function ($scope, $location, boardFactory, BoardService) {
    $scope.boards = null;
    $scope.newBoard = {};
    $scope.status = {};
    $scope.colors = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];

    $scope.boardservice = BoardService;
    BoardService.fetchAll().then(function(data) {
        console.log($scope.boardservice);
        console.log(data);
    }, function(error) {
        //$scope.setStatus('error','Error occured', error);
    });

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
        $http.put(this.baseUrl, entity).then(function (response) {
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
    service = new StackService($http, 'stacks', $q)
    return service;
});


