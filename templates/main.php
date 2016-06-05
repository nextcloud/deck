<?php

use OCP\Util;

Util::addStyle('deck', 'font-awesome');
Util::addStyle('deck', 'style');
Util::addStyle('deck', '../js/vendor/ng-sortable/dist/ng-sortable.min');
Util::addStyle('deck', '../js/vendor/ng-sortable/dist/ng-sortable.style.min');
Util::addScript('deck', 'vendor/angular/angular.min');
Util::addScript('deck', 'vendor/angular-route/angular-route.min');
Util::addScript('deck', 'vendor/angular-sanitize/angular-sanitize.min');
Util::addScript('deck', 'vendor/angular-animate/angular-animate.min');
Util::addScript('deck', 'vendor/angular-ui-router/release/angular-ui-router.min');
Util::addScript('deck', 'vendor/ng-sortable/dist/ng-sortable.min');
Util::addScript('deck', 'public/app');

?>

<div id="app" class="app-deck" data-ng-app="Deck" ng-controller="AppController" ng-cloak>

	<div id="app-navigation" data-ng-controller="ListController">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php print_unescaped($this->inc('part.settings')); ?>
	</div>
	<route-loading-indicator />
	<div id="app-content" ui-view  ng-class="{ 'details-visible': sidebar.show }" ng-if='!isRouteLoading' >
	</div>


	<script type="text/ng-template" id="/boardlist.mainView.html">
		<?php print_unescaped($this->inc('part.boardlist')); ?>
	</script>
	<script type="text/ng-template" id="/boardlist.sidebarView.html">
		<?php print_unescaped($this->inc('part.empty')); ?>
	</script>
	<script type="text/ng-template" id="/board.mainView.html">
		<?php print_unescaped($this->inc('part.board.mainView')); ?>
	</script>
	<script type="text/ng-template" id="/board.html">
		<?php print_unescaped($this->inc('part.board')); ?>
	</script>
	<script type="text/ng-template" id="/board.sidebarView.html">
		<?php print_unescaped($this->inc('part.empty')); ?>
	</script>
	<script type="text/ng-template" id="/card.sidebarView.html">
		<?php print_unescaped($this->inc('part.card')); ?>
	</script>


</div>
