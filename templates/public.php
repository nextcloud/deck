<?php

use OCP\Util;
Util::addStyle('deck', 'font-awesome');
Util::addStyle('deck', 'style');
Util::addScript('deck', 'vendor/angular/angular.min');
Util::addScript('deck', 'vendor/angular-route/angular-route.min');
Util::addScript('deck', 'vendor/angular-sanitize/angular-sanitize.min');
Util::addScript('deck', 'vendor/angular-animate/angular-animate.min');
Util::addScript('deck', 'vendor/angular-ui-router/release/angular-ui-router.min');
Util::addScript('deck', 'app/App');

?>

<div id="app" class="app-deck public-board" data-ng-app="Deck" ng-controller="AppController" ng-cloak">

<div id="app-content" ng-class="{ 'details-visible': sidebar.show }">
    <?php print_unescaped($this->inc('part.content')); ?>
</div>

<div id="app-sidebar" class="details-view scroll-container" ng-controller="CardController" ng-class="{ 'details-visible': sidebar.show }">
    <?php print_unescaped($this->inc('part.card')); ?>
</div>
</div>
