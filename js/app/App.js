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

