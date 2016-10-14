<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use OCP\Util;

Util::addStyle('deck', 'style');
Util::addStyle('deck', '../js/vendor/ng-sortable/dist/ng-sortable.min');
Util::addStyle('deck', '../js/vendor/angular-ui-select/dist/select.min');
Util::addScript('deck', 'vendor/angular/angular.min');
Util::addScript('deck', 'vendor/angular-route/angular-route.min');
Util::addScript('deck', 'vendor/angular-sanitize/angular-sanitize.min');
Util::addScript('deck', 'vendor/angular-animate/angular-animate.min');
Util::addScript('deck', 'vendor/angular-ui-router/release/angular-ui-router.min');
Util::addScript('deck', 'vendor/ng-sortable/dist/ng-sortable.min');
Util::addScript('deck', 'vendor/angular-ui-select/dist/select.min');
Util::addScript('deck', 'vendor/showdown/dist/showdown.min');
Util::addScript('deck', 'public/app');

?>

<div id="app" class="app-deck" data-ng-app="Deck" ng-controller="AppController" ng-cloak>

	<div id="app-navigation" data-ng-controller="ListController">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php /* print_unescaped($this->inc('part.settings'));*/ ?>
	</div>
	<div id="app-content" ng-class="{ 'details-visible': sidebar.show }">
		<div id="app-view" ui-view></div>
	</div>
	<route-loading-indicator></route-loading-indicator>


	<script type="text/ng-template" id="/boardlist.mainView.html">
		<?php print_unescaped($this->inc('part.boardlist')); ?>
	</script>
	<script type="text/ng-template" id="/boardlist.sidebarView.html"></script>
	<script type="text/ng-template" id="/board.sidebarView.html">
		<?php print_unescaped($this->inc('part.board.sidebarView')); ?>
	</script>
	<script type="text/ng-template" id="/board.mainView.html">
		<?php print_unescaped($this->inc('part.board.mainView')); ?>
	</script>
	<script type="text/ng-template" id="/board.html">
		<?php print_unescaped($this->inc('part.board')); ?>
	</script>
	<script type="text/ng-template" id="/card.sidebarView.html">
		<?php print_unescaped($this->inc('part.card')); ?>
	</script>

</div>
