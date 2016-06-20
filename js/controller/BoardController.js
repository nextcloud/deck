
app.controller('BoardController', function ($rootScope, $scope, $stateParams, StatusService, BoardService, StackService, CardService) {

  $scope.sidebar = $rootScope.sidebar;

  $scope.id = $stateParams.boardId;

  $scope.stackservice = StackService;
  $scope.boardservice = BoardService;
  $scope.statusservice = StatusService.getInstance();


  // fetch data
  StackService.clear();
  $scope.statusservice.retainWaiting();
  $scope.statusservice.retainWaiting();

  console.log("foo");
  StackService.fetchAll($scope.id).then(function(data) {
    console.log(data);

    $scope.statusservice.releaseWaiting();
  }, function(error) {
    $scope.statusservice.setError('Error occured', error);
  });

  BoardService.fetchOne($scope.id).then(function(data) {

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
      // TODO: Implement reodering here
      event.source.itemScope.modelValue.status = event.dest.sortableScope.$parent.column;
      var order = event.dest.index;
      var card = event.source.itemScope.c;
      var newStack = event.dest.sortableScope.$parent.s.id;
      card.stackId = newStack;
      CardService.update(card);

      CardService.reorder(card, order).then(function(data) {
        StackService.data[newStack].cards = data;
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

});
