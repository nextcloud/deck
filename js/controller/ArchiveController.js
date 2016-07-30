
app.controller('ArchiveController', function ($rootScope, $scope, $stateParams, StatusService, BoardService, StackService, CardService, LabelService, $state, $transitions) {

  $scope.sidebar = $rootScope.sidebar;

  $scope.id = $stateParams.boardId;
  $scope.status={},
  $scope.newLabel={};
  $scope.status.boardtab = $stateParams.detailTab;
  $scope.state = $state.current;

  console.log($scope.state);
  $scope.stackservice = StackService;
  $scope.boardservice = BoardService;
  $scope.cardservice = CardService;
  $scope.statusservice = StatusService.getInstance();
  $scope.labelservice = LabelService;

  $scope.foo = function(state) {
    console.log(state);
  }


  // fetch data
  StackService.clear();
  $scope.statusservice.retainWaiting();
  $scope.statusservice.retainWaiting();

  BoardService.fetchOne($scope.id).then(function(data) {
    $scope.statusservice.releaseWaiting();
  }, function(error) {
    $scope.statusservice.setError('Error occured', error);
  });

  console.log($scope);
    StackService.fetchArchived($scope.id).then(function(data) {
      console.log(data);
      $scope.statusservice.releaseWaiting();
    }, function(error) {
      $scope.statusservice.setError('Error occured', error);
    });





  BoardService.searchUsers();




  $scope.cardDelete = function(card) {
    CardService.delete(card.id);
    StackService.deleteCard(card);

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

});
