app.directive('search', function ($document, $location) {
	'use strict';

	return {
		restrict: 'E',
		scope: {
			'onSearch': '='
		},
		link: function (scope) {
			var box = $('#searchbox');
			box.val($location.search().search);

			var doSearch = function() {
				var value = box.val();
				scope.$apply(function () {
					scope.onSearch(value);
				});
			}

			box.on('search keyup', function (event) {
				if (event.type === 'search' || event.keyCode === 13 ) {
					doSearch();
				}
			});

		}
	};
});
