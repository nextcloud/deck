describe('BoardController', function() {
  'use strict';

  var $controller;

  beforeEach(inject(function(_$controller_){
    // The injector unwraps the underscores (_) from around the parameter names when matching
    $controller = _$controller_;
  }));

  describe('$scope.rgblight', function() {
    it('converts rbg color to a lighter color', function() {
      var $scope = {};
      var controller = $controller('BoardController', { $scope: $scope });
      var hex = $scope.rgblight('red');
      expect(hex).toEqual('#red');
    });
  });
});
