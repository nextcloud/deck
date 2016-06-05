
    <?php print_unescaped($this->inc('part.board.mainView')); ?>
    <route-loading-indicator></route-loading-indicator>

    <div id="app-sidebar" class="details-view scroll-container" ng-class="{ 'details-visible': sidebar.show }" ui-view="sidebarView" ng-controller="CardController">
    </div>
