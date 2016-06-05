var app = angular.module('Deck', ['ngRoute', 'ngSanitize', 'ui.router', 'as.sortable']);

app.config(function ($provide, $routeProvider, $interpolateProvider, $httpProvider, $urlRouterProvider, $stateProvider) {
    'use strict';
    $httpProvider.defaults.headers.common.requesttoken = oc_requesttoken;

    $urlRouterProvider.otherwise("/");

    $stateProvider
        .state('list', {
            url: "/",
            templateUrl: "/boardlist.mainView.html",
            controller: 'ListController',
        })
        .state('board', {
            url: "/board/:boardId",
            templateUrl: "/board.html",
            controller: 'BoardController'
        })
        .state('board.card', {
            url: "/card/:cardId",
            views: {
                "sidebarView": {
                    templateUrl: "/card.sidebarView.html",
                    controller: 'CardController'
                }
            }
        })
        .state('board.settings', {})
        .state('board.sharing', {});
});

// OwnCloud Click Handling
// https://doc.owncloud.org/server/8.0/developer_manual/app/css.html
app.directive('appNavigationEntryUtils', function () {
    'use strict';
    return {
        restrict: 'C',
        link: function (scope, elm) {

            var menu = elm.siblings('.app-navigation-entry-menu');
            var button = $(elm)
                .find('.app-navigation-entry-utils-menu-button button');

            button.click(function () {
                menu.toggleClass('open');
            });
            scope.$on('documentClicked', function (scope, event) {
                if (event.target !== button[0]) {
                    menu.removeClass('open');
                }
            });
        }
    };
});

app.directive('autofocusOnInsert', function () {
    'use strict';
    return function (scope, elm) {
        elm.focus();
    };
});

app.run(function ($document, $rootScope, $transitions) {
    'use strict';
    $document.click(function (event) {
        $rootScope.$broadcast('documentClicked', event);
    });
    $transitions.onEnter({to: 'board.card'}, function ($state, $transition$) {
        $rootScope.sidebar.show = true;
    });
    $transitions.onEnter({to: 'board'}, function ($state) {
        $rootScope.sidebar.show = false;
    });
    $transitions.onExit({from: 'board.card'}, function ($state) {
        $rootScope.sidebar.show = false;
    });
});