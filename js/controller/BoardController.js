
app.controller('BoardController', function ($rootScope, $scope, $location, $http, $route, $stateParams, boardFactory, stackFactory, StackService) {

  $scope.sidebar = $rootScope.sidebar;
  $scope.newCard = {};
  $scope.stackservice = StackService;
  $scope.id = $stateParams.boardId;

  $scope.status = {
    'active': true,
    'icon': 'loading',
    'title': 'Bitte warten',
    'text': 'Es dauert noch einen kleinen Moment'
  };

  $scope.setStatus = function($icon, $title, $text='') {
    $scope.status.active = true;
    $scope.status.icon = $icon;
    $scope.status.title = $title;
    $scope.status.text = $text;
  }

  $scope.unsetStatus = function() {
    $scope.status = {
      'active': false
    }
  }

  $scope.getBoard = function() {
    boardFactory.getBoard($scope.id)
        .then(function (response) {
          $scope.board = response.data;
          $scope.unsetStatus();
        }, function (error) {
          $scope.setStatus('error','Unable to load board data', error);
        });
  }

  $scope.getStacks = function() {
    stackFactory.getStacks($scope.id)
        .then(function (response) {

          $scope.stacks = response.data;
          $scope.unsetStatus();
        }, function (error) {
          $scope.setStatus('error','Unable to load board data', error);
        });
  }

  $scope.createStack = function () {
    $scope.newStack.boardId = $scope.id;
    stackFactory.createStack($scope.newStack)
        .then(function (response) {
          $scope.stacks.push(response.data);
          $scope.newStack = {};
          $scope.status.addStack=false;
        }, function(error) {
          $scope.status.createBoard = 'Unable to insert board: ' + error.message;
        });
  };

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

  $scope.sortStackOptions = {
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


  $scope.getBoard();
  $scope.getStacks();


});
