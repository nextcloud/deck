
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
