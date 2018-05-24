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

Util::addStyle('deck', '../js/build/vendor');
Util::addScript('deck', 'build/vendor');

Util::addStyle('deck', 'style');
Util::addScript('deck', 'build/deck');
?>

<div id="app" class="app-deck" data-ng-app="Deck" ng-controller="AppController" ng-cloak>
	<keyboard-events></keyboard-events>
	<div id="app-navigation" data-ng-controller="ListController" ng-init="initSidebar()">
		<?php print_unescaped($this->inc('part.navigation')); ?>
		<?php /* print_unescaped($this->inc('part.settings')); */ ?>
	</div>
	<div id="app-content" ng-class="{ 'details-visible': sidebar.show }">
		<div id="shortcuts" style="display: none;">
			<h2>Shortcuts</h2>
			<dl>
				<h3>Board</h3>
				<div>
					<dt><kbd>A</kbd></dt>
					<dd>Mark as archived / unarchived</dd>
				</div>
				<div>
					<dt><kbd>E</kbd></dt>
					<dd>Edit title of a card</dd>
				</div>
				<div>
					<dt><kbd>D</kbd></dt>
					<dd>Edit description of a card</dd>
				</div>
				<div>
					<dt><kbd>L</kbd></dt>
					<dd>Apply labels to a card</dd>
				</div>
				<div>
					<dt><kbd>U</kbd></dt>
					<dd>Assign users to a card</dd>
				</div>
				<div>
					<dt><kbd>D</kbd></dt>
					<dd>Set a due date</dd>
				</div>
				<div>
					<dt>
						<kbd>←</kbd> <kbd>←</kbd> <kbd>←</kbd> <kbd>←</kbd><br />
						<kbd>h</kbd> <kbd>j</kbd> <kbd>k</kbd> <kbd>l</kbd>
					</dt>
					<dd>Mark as archived / unarchived</dd>
				</div>
			</dl>
			<dl>
				<h3>Global</h3>
				<div>
					<dt><kbd>Ctrl</kbd> + <kbd>F</kbd></dt>
					<dd>Search</dd>
				</div>
				<div>
					<dt><kbd>P</kbd></dt>
					<dd>Go to board preferences</dd>
				</div>
				<div>
					<dt><kbd>C</kbd></dt>
					<dd>Create a new card in the current / first stack</dd>
				</div>
				<div>
					<dt><kbd>S</kbd></dt>
					<dd>Create a new stack</dd>
				</div>
			</dl>
		</div>
		<div ui-view></div>
	</div>
	<route-loading-indicator></route-loading-indicator>



	<script type="text/ng-template" id="/boardlist.mainView.html">
		<?php print_unescaped($this->inc('part.boardlist')); ?>
	</script>
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
