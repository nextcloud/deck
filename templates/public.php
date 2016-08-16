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
