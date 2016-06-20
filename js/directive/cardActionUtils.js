// OwnCloud Click Handling
// https://doc.owncloud.org/server/8.0/developer_manual/app/css.html
app.directive('cardActionUtils', function () {
    'use strict';
    return {
        restrict: 'C',
        scope: {
            ngModel : '=',
        },
        link: function (scope, elm) {
            console.log(scope);
/*
            var menu = elm.siblings('.popovermenu');
            var button = $(elm)
                .find('li a');

            button.click(function () {
                menu.toggleClass('open');
            });
            scope.$on('documentClicked', function (scope, event) {
                if (event.target !== button[0]) {
                    menu.removeClass('open');
                }
            });
            */
        }
    };
});

