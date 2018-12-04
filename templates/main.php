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

Util::addScript('activity', 'richObjectStringParser');
if (\OC_Util::getVersion()[0] > 14) {
	Util::addScript('activity', 'templates');
}

Util::addStyle('activity', 'style');
Util::addStyle('comments', 'comments');
Util::addScript('oc-backbone-webdav');

Util::addStyle('deck', '../js/build/vendor');
Util::addScript('deck', 'build/vendor');

Util::addStyle('deck', 'style');
Util::addScript('deck', 'build/deck');

if (\OC_Util::getVersion()[0] < 14) {
	Util::addStyle('deck', 'comp-13');
}
?>

<div
		class="app app-deck"
		data-ng-app="Deck"
		ng-controller="AppController"
		ng-cloak
		config="<?php p(json_encode($_)); ?>"
		ng-class="{'app-navigation-hide': appNavigationHide, 'compact-mode': compactMode}">

	<div id="app-navigation" data-ng-controller="ListController" ng-init="initSidebar()">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php print_unescaped($this->inc('part.settings')); ?>
	</div>
	<div id="app-content" ng-class="{ 'details-visible': sidebar.show }"><div id="app-navigation-toggle-custom" class="icon-menu" ng-click="toggleSidebar()"></div><div ui-view></div></div>
	<div id="app-sidebar" ng-class="{ 'details-visible': sidebar.show }" ng-if="sidebar.show" class="details-view scroll-container" ui-view="sidebarView"></div>
	<route-loading-indicator></route-loading-indicator>

	<script type="text/ng-template" id="/boardlist.mainView.html">
		<?php print_unescaped($this->inc('part.boardlist')); ?>
	</script>
	<script type="text/ng-template" id="/board.sidebarView.html">
		<?php print_unescaped($this->inc('part.board.sidebarView')); ?>
	</script>
	<script type="text/ng-template" id="/board.html">
		<?php print_unescaped($this->inc('part.board.mainView')); ?>
	</script>
	<script type="text/ng-template" id="/card.sidebarView.html">
		<?php print_unescaped($this->inc('part.card')); ?>
	</script>
	<script type="text/ng-template" id="/card.attachments.html">
		<?php print_unescaped($this->inc('part.card.attachments')); ?>
	</script>

</div>
