app.config(function ($provide, $routeProvider, $interpolateProvider, $httpProvider, $urlRouterProvider, $stateProvider, $compileProvider, markdownProvider) {
    'use strict';
    $httpProvider.defaults.headers.common.requesttoken = oc_requesttoken;


    markdownProvider.config({
        simplifiedAutoLink: true,
        strikethrough: true,
        tables: true,
        tasklists: true

    });

    $compileProvider.debugInfoEnabled(true);

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
        .state('board.detail', {
            url: "/detail/",
            views: {
                "sidebarView": {
                    templateUrl: "/board.sidebarView.html",
                }
            }
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
        .state('board.settings', {

        })
        .state('board.sharing', {
            
        });


});