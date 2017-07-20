(function(window, angular) {
  'use strict';
  function markdownItProvider() {
    var options = {};
    var presetName = 'default';
    var plugins = [];
    function markdownItFactory(markdownIt) {
      var md = markdownIt(presetName, options);
      for (var i = 0; i < plugins.length; i += 1) {
        md.use.apply(md, plugins[i]);
      }
      return md;
    }
    this.config = function configureOptions(preset, opts) {
      if (angular.isString(preset) && angular.isObject(opts)) {
        presetName = preset;
        options = opts;
      } else if (angular.isString(preset)) {
        presetName = preset;
      } else if (angular.isObject(preset)) {
        options = preset;
      }
    };
    this.use = function addPlugin(pluginObject) {
      var options = Array.prototype.slice.call(arguments);
      plugins.push(options);
      return this;
    };
    this.$get = [ '$log', function($log) {
      var constructor = window.markdownit || markdownit;
      if (angular.isFunction(constructor)) {
        return markdownItFactory(constructor);
      }
      $log.error('angular-markdown-it: markdown-it library not loaded.');
    } ];
  }
  function markdownItDirective($sanitize, markdownIt) {
    var attribute = 'markdownIt';
    var render = function(value) {
      return value ? $sanitize(markdownIt.render(value)) : '';
    };
    var link = function(scope, element, attrs) {
      if (attrs[attribute]) {
        scope.$watch(attribute, function(value) {
          element.html(render(value));
        });
      } else {
        element.html(render(element.text()));
      }
    };
    return {
      restrict: 'AE',
      scope: {
        markdownIt: '='
      },
      replace: true,
      link: link
    };
  }
  angular.module('mdMarkdownIt', [ 'ngSanitize' ]).provider('markdownItConverter', markdownItProvider).directive('markdownIt', [ '$sanitize', 'markdownItConverter', markdownItDirective ]);
})(window, window.angular);