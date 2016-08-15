app.directive('avatar', function() {
	'use strict';
	return {
		restrict: 'A',
		scope: true,
		link: function(scope, element, attr){
			attr.$observe('displayname', function(value){
				console.log(value);
				if(value!==undefined) {
					$(element).avatar(value, 32);
				}
			});

		}
	};
});