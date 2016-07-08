
angular.module('markdown', [])
	.provider('markdown', [function () {
		var opts = {};
		return {
			config: function (newOpts) {
				opts = newOpts;
			},
			$get: function () {
				return new window.showdown.Converter(opts);
			}
		};
	}])
	.filter('markdown', ['markdown', function (markdown) {
		return function (text) {
			return markdown.makeHtml(text || '');
		};
	}]);

var app = angular.module('Deck', [
	'ngRoute',
	'ngSanitize',
	'ui.router',
	'ui.select',
	'as.sortable',
	'markdown',
	'ngAnimate'
]);


app.config(["$provide", "$routeProvider", "$interpolateProvider", "$httpProvider", "$urlRouterProvider", "$stateProvider", "$compileProvider", "markdownProvider", function ($provide, $routeProvider, $interpolateProvider, $httpProvider, $urlRouterProvider, $stateProvider, $compileProvider, markdownProvider) {
    'use strict';
    $httpProvider.defaults.headers.common.requesttoken = oc_requesttoken;


    markdownProvider.config({
        simplifiedAutoLink: true,
        strikethrough: true,
        tables: true,
        tasklists: true,

    });

    $compileProvider.debugInfoEnabled(true);

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
        .state('board.detail', {
            url: "/detail/",
            views: {
                "sidebarView": {
                    templateUrl: "/board.sidebarView.html",
                }
            }
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
        .state('board.settings', {

        })
        .state('board.sharing', {
            
        });
}]);
app.run(["$document", "$rootScope", "$transitions", function ($document, $rootScope, $transitions) {
    'use strict';
    $document.click(function (event) {
        $rootScope.$broadcast('documentClicked', event);
    });
    $transitions.onEnter({to: 'board.card'}, function ($state, $transition$) {
        $rootScope.sidebar.show = true;
    });
    $transitions.onEnter({to: 'board.detail'}, function ($state, $transition$) {
        $rootScope.sidebar.show = true;
    });
    $transitions.onEnter({to: 'board'}, function ($state) {
        $rootScope.sidebar.show = false;
    });
    $transitions.onExit({from: 'board.card'}, function ($state) {
        $rootScope.sidebar.show = false;
    });
    $transitions.onExit({from: 'board.detail'}, function ($state) {
        $rootScope.sidebar.show = false;
    });

    $('link[rel="shortcut icon"]').attr(
        'href',
        OC.filePath('deck', 'img', 'app-512.png')
    );

}]);


app.controller('AppController', ["$scope", "$location", "$http", "$route", "$log", "$rootScope", "$stateParams", function ($scope, $location, $http, $route, $log, $rootScope, $stateParams) {
    $rootScope.sidebar = {
        show: false
    };
    $scope.sidebar = $rootScope.sidebar;
}]);

app.controller('BoardController', ["$rootScope", "$scope", "$stateParams", "StatusService", "BoardService", "StackService", "CardService", "LabelService", function ($rootScope, $scope, $stateParams, StatusService, BoardService, StackService, CardService, LabelService) {

  $scope.sidebar = $rootScope.sidebar;

  $scope.id = $stateParams.boardId;
  $scope.status={},
      $scope.newLabel={};
  $scope.status.boardtab = $stateParams.detailTab;

  $scope.stackservice = StackService;
  $scope.boardservice = BoardService;
  $scope.statusservice = StatusService.getInstance();
  $scope.labelservice = LabelService;
  $scope.defaultColors = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];


  // fetch data
  StackService.clear();
  $scope.statusservice.retainWaiting();
  $scope.statusservice.retainWaiting();

  StackService.fetchAll($scope.id).then(function(data) {
    console.log(data);

    $scope.statusservice.releaseWaiting();
  }, function(error) {
    $scope.statusservice.setError('Error occured', error);
  });

  BoardService.searchUsers();

  BoardService.fetchOne($scope.id).then(function(data) {
    console.log(BoardService.getCurrent());
    $scope.statusservice.releaseWaiting();
  }, function(error) {
    $scope.statusservice.setError('Error occured', error);
  });

  $scope.newStack = { 'boardId': $scope.id};
  $scope.newCard = {};



  // Create a new Stack
  $scope.createStack = function () {
    StackService.create($scope.newStack).then(function (data) {
      $scope.newStack.title="";
    });
  };

  $scope.createCard = function(stack, title) {
    var newCard = {
      'title': title,
      'stackId': stack,
      'type': 'plain',
    };
    CardService.create(newCard).then(function (data) {
      $scope.stackservice.addCard(data);
      $scope.newCard.title = "";
    });
  }

  $scope.cardDelete = function(card) {
    CardService.delete(card.id);
    StackService.deleteCard(card);

  }

  $scope.labelDelete = function(label) {
    LabelService.delete(label.id);
    // remove from board data
    var i = BoardService.getCurrent().labels.indexOf(label);
    BoardService.getCurrent().labels.splice(i, 1);
    // TODO: remove from cards
  }
  $scope.labelCreate = function(label) {
    label.boardId = $scope.id;
    LabelService.create(label);
    BoardService.getCurrent().labels.push(label);
    $scope.status.createLabel = false;
    $scope.newLabel = {};
  }
  $scope.labelUpdate = function(label) {
    label.edit = false;
    LabelService.update(label);
  }

  $scope.addSharee = function(sharee) {
    sharee.boardId = $scope.id;
    BoardService.addSharee(sharee);
    $scope.status.addSharee = null;
  }
  $scope.deleteAcl = function(acl) {
    acl.boardId = $scope.id;
    BoardService.removeAcl(acl);
  }
  // TODO: move to filter?
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

  // TODO: move to filter?
  // RGB2HLS by Garry Tan
  // http://axonflux.com/handy-rgb-to-hsl-and-rgb-to-hsv-color-model-c
  $scope.textColor = function (hex) {
    var result = /^([A-Fa-f\d]{2})([A-Fa-f\d]{2})([A-Fa-f\d]{2})$/i.exec(hex);
    var color = result ? {
      r: parseInt(result[1], 16),
      g: parseInt(result[2], 16),
      b: parseInt(result[3], 16)
    } : null;
    if(result !== null) {
      r = color.r/255;
      g = color.g/255;
      b = color.b/255;
      var max = Math.max(r, g, b), min = Math.min(r, g, b);
      var h, s, l = (max + min) / 2;

      if(max == min){
        h = s = 0; // achromatic
      }else{
        var d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch(max){
          case r: h = (g - b) / d + (g < b ? 6 : 0); break;
          case g: h = (b - r) / d + 2; break;
          case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
      }
      // TODO: Maybe just darken/lighten the color
      if(l<0.5) {
        return "#ffffff";
      } else {
        return "#000000";
      }
      //var rgba = "rgba(" + color.r + "," + color.g + "," + color.b + ",0.7)";
      //return rgba;
    } else {
      return "#aa0000";
    }
  };



  // settings for card sorting
  $scope.sortOptions = {
    itemMoved: function (event) {
      // TODO: Implement reodering here
      event.source.itemScope.modelValue.status = event.dest.sortableScope.$parent.column;
      var order = event.dest.index;
      var card = event.source.itemScope.c;
      var newStack = event.dest.sortableScope.$parent.s.id;
      card.stackId = newStack;
      CardService.update(card);

      CardService.reorder(card, order).then(function(data) {
        StackService.data[newStack].addCard(card);
      });
    },
    orderChanged: function (event) {
      // TODO: Implement ordering here
      var order = event.dest.index;
      var card = event.source.itemScope.c;
      CardService.reorder(card, order);
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

}]);



app.controller('CardController', ["$scope", "$rootScope", "$routeParams", "$location", "$stateParams", "BoardService", "CardService", "StackService", "StatusService", function ($scope, $rootScope, $routeParams, $location, $stateParams, BoardService, CardService, StackService, StatusService) {
    $scope.sidebar = $rootScope.sidebar;
    $scope.status = {};

    $scope.cardservice = CardService;
    $scope.cardId = $stateParams.cardId;

    $scope.statusservice = StatusService.getInstance();
    $scope.boardservice = BoardService;

    $scope.statusservice.retainWaiting();

    CardService.fetchOne($scope.cardId).then(function(data) {
        $scope.statusservice.releaseWaiting();

        console.log(data);
    }, function(error) {
    });


    // handle rename to update information on the board as well
    $scope.renameCard = function(card) {
        CardService.rename(card).then(function(data) {
            StackService.updateCard(card);
            $scope.status.renameCard = false;
        });
    };

    $scope.updateCard = function(card) {
        CardService.update(CardService.getCurrent());
        $scope.status.description = false;
    }

    $scope.editDescription = function() {
        $scope.status.description = true;
    }

    $scope.labelAssign = function(element, model) {
        CardService.assignLabel($scope.cardId, element.id);
        var card = CardService.getCurrent();
        StackService.updateCard(card);
    }
    $scope.labelRemove = function(element, model) {
        CardService.removeLabel($scope.cardId, element.id)
    }

    /*var menu = $('#app-content');
     menu.click(function(event){
     $scope.location.path('/board/'+$scope.boardId);
     $scope.$apply();

     });*/
}]);


app.controller('ListController', ["$scope", "$location", "BoardService", function ($scope, $location, BoardService) {
    $scope.boards = null;
    $scope.newBoard = {};
    $scope.status = {};
    $scope.colors = ['31CC7C', '317CCC', 'FF7A66', 'F1DB50', '7C31CC', 'CC317C', '3A3B3D', 'CACBCD'];

    $scope.boardservice = BoardService;

    BoardService.fetchAll(); // TODO: show error when loading fails

    $scope.selectColor = function(color) {
        $scope.newBoard.color = color;
    };

    $scope.createBoard = function () {
        BoardService.create($scope.newBoard)
            .then(function (response) {
                $scope.newBoard = {};
                $scope.status.addBoard=false;
            }, function(error) {
                $scope.status.createBoard = 'Unable to insert board: ' + error.message;
            });
    };
    $scope.updateBoard = function(board) {
        BoardService.update(board);
        board.status.edit = false;
    };
    $scope.deleteBoard = function(board) {
        // TODO: Ask for confirmation
        //if (confirm('Are you sure you want to delete this?')) {
            BoardService.delete(board.id);
        //}
    };




}]);


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
app.directive('avatar', function() {
	'use strict';
	return {
		restrict: 'A',
		scope: false,
		link: function(scope, elm, attr) {
			return attr.$observe('user', function() {
				if (attr.user) {
					var url = OC.generateUrl('/avatar/{user}/{size}',
						{user: attr.user, size: Math.ceil(attr.size * window.devicePixelRatio)});
					var inner = '<img src="'+url+'" />';
					elm.html(inner);
					//elm.avatar(attr.user, attr.size);
				}
			});
		}
	};
});
// OwnCloud Click Handling
// https://doc.owncloud.org/server/8.0/developer_manual/app/css.html
app.directive('cardActionUtils', function () {
    'use strict';
    return {
        restrict: 'C',
        scope: {
            ngModel : '=',
        },
        link: function (scope, elm) {
            console.log(scope);
/*
            var menu = elm.siblings('.popovermenu');
            var button = $(elm)
                .find('li a');

            button.click(function () {
                menu.toggleClass('open');
            });
            scope.$on('documentClicked', function (scope, event) {
                if (event.target !== button[0]) {
                    menu.removeClass('open');
                }
            });
            */
        }
    };
});


// original idea from blockloop: http://stackoverflow.com/a/24090733
app.directive('elastic', [
	'$timeout',
	function($timeout) {
		return {
			restrict: 'A',
			link: function($scope, element) {
				$scope.initialHeight = $scope.initialHeight || element[0].style.height;
				var resize = function() {
					element[0].style.height = $scope.initialHeight;
					element[0].style.height = "" + element[0].scrollHeight + "px";
				};
				element.on("input change", resize);
				$timeout(resize, 0);
			}
		};
	}
]);
app.factory('ApiService', ["$http", "$q", function($http, $q){
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

}]);

app.factory('BoardService', ["ApiService", "$http", "$q", function(ApiService, $http, $q){
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
    
}]);
app.factory('CardService', ["ApiService", "$http", "$q", function(ApiService, $http, $q){
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
}]);
app.factory('LabelService', ["ApiService", "$http", "$q", function(ApiService, $http, $q){
    var LabelService = function($http, ep, $q) {
        ApiService.call(this, $http, ep, $q);
    };
    LabelService.prototype = angular.copy(ApiService.prototype);
    service = new LabelService($http, 'labels', $q)
    return service;
}]);
app.factory('StackService', ["ApiService", "$http", "$q", function(ApiService, $http, $q){
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
}]);


app.factory('StatusService', function(){
    // Status Helper
    var StatusService = function() {
        this.active = true;
        this.icon = 'loading';
        this.title = 'Please wait';
        this.text = 'Es dauert noch einen kleinen Moment';
        this.counter = 0;
    }


    StatusService.prototype.setStatus = function($icon, $title, $text) {
        this.active = true;
        this.icon = $icon;
        this.title = $title;
        this.text = $text;
    }

    StatusService.prototype.setError = function($title, $text) {
        this.active = true;
        this.icon = 'error';
        this.title = $title;
        this.text = $text;
        this.counter = 0;
    }

    StatusService.prototype.releaseWaiting = function() {
        if(this.counter>0)
            this.counter--;
        if(this.counter<=0) {
            this.active = false;
            this.counter = 0;
        }
    }

    StatusService.prototype.retainWaiting = function() {
        this.active = true;
        this.icon = 'loading';
        this.title = 'Please wait';
        this.text = 'Es dauert noch einen kleinen Moment';
        this.counter++;
    }

    StatusService.prototype.unsetStatus = function() {
        this.active = false;
    }

    return {
        getInstance: function() {
            return new StatusService();
        }
    }

});



