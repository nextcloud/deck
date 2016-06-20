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