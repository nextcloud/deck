<ul class="with-icon">

	<li ng-class="{active: status.filter === '' && !boardservice.getCurrent()}"><a ui-sref="list({ filter: ''})" class="icon-deck"><?php p($l->t('All Boards')); ?></a></li>
	<li ng-class="{active: status.filter === 'archived' || (boardservice.getCurrent() && boardservice.getCurrent().archived)}"><a ui-sref="list({ filter: 'archived' })" class="icon-archive"><?php p($l->t('Archived boards')); ?></a></li>
	<li ng-class="{active: status.filter === 'shared'}"><a ui-sref="list({ filter: 'shared' })" class="icon-share"><?php p($l->t('Shared boards')); ?></a></li>

	<li class="with-icon with-menu" ng-class="{active: b.id === boardservice.getCurrent().id, editing: b.status.editNavigation}" data-ng-repeat="b in boardservice.sidebar track by b.id" ng-if="b.deletedAt == 0">

		<span class="board-bullet"  ng-style="{'background-color': '#' + b.color}"> </span>
		<a href="#!/board/{{b.id}}/">{{ b.title }}</a>
		<div class="app-navigation-entry-utils">
			<ul>
				<li class="app-navigation-entry-utils-menu-button svg" ng-show="!status.deleteUndo[b.id]"><button class="icon-more" title="<?php p($l->t('View more')); ?>"><span class="hidden-visually"><?php p($l->t('View more')); ?></span></button></li>
			</ul>
		</div>
		<div class="app-navigation-entry-menu" ng-show="!b.status.editNavigation">
			<ul>
				<li ng-show="boardservice.canManage(b)">
					<a class="icon-rename" title="<?php p($l->t('Edit board')); ?>" ng-click="b.status.editNavigation=true">
						<?php p($l->t('Edit board')); ?>
					</a>
				</li>
				<li ng-show="boardservice.canManage(b)">
					<a class="icon-archive" title="<?php p($l->t('Move board to archive')); ?>" ng-click="boardArchive(b)">
						<?php p($l->t('Archive board')); ?>
					</a>
				</li>
				<li ui-sref="board.detail({boardId: b.id})">
					<a class="icon-settings-dark">
						<?php p($l->t('Board details')); ?>
					</a>
				</li>
			</ul>
		</div>

		<div class="app-navigation-entry-edit">
			<form ng-disabled="isAddingList" class="ng-pristine ng-valid"  ng-submit="boardUpdate(b)">
				<input class="edit ng-valid ng-empty" type="text" autofocus-on-insert ng-model="b.title" maxlength="100" ng-model-options="{ debounce: 250 }">
				<input type="submit" value="" class="action icon-checkmark svg">
			</form>
			<div class="colorselect" ng-controller="ColorPickerController">
				<div class="color" ng-repeat="c in ::colors" ng-style="{'background-color':'#{{ c }}'}" ng-click="b=setColor(b,c)" ng-class="{'selected': (c == b.color) }"></div>
                <label class="colorselect-label{{ b.color | iconWhiteFilter }} color" ng-style="getCustomBackground(b.hashedColor)" ng-init="b.hashedColor='#' + b.color">
                    <input class="color" type="color" ng-model="b.hashedColor" value="#{{b.color}}" ng-change="b=setHashedColor(b)"/>
                </label>
			</div>
		</div>
	</li>

	<li ng-class="{editing: status.addBoard}">
		<a ng-click="status.addBoard=!status.addBoard" class="icon-add app-navigation-noclose">
			<?php p($l->t('Create a new board')); ?>
		</a>
		<div class="app-navigation-entry-edit" ng-if="status.addBoard">
			<form ng-disabled="isAddingList" class="ng-pristine ng-valid"  ng-submit="boardCreate()">
				<input class="edit ng-valid ng-empty" type="text" placeholder="<?php p($l->t('New board title')); ?>" autofocus-on-insert ng-model="newBoard.title" maxlength="100" ng-model-options="{ debounce: 250 }">
				<input type="submit" value="" class="action icon-checkmark svg">
			</form>
			<div class="colorselect" ng-controller="ColorPickerController">
				<div class="color" ng-repeat="c in ::colors" ng-style="{'background-color':'#{{ c }}'}" ng-click="selectColor(c);newBoard=setColor(newBoard,c)" ng-class="{'selected': (c == newBoard.color), 'dark': (newBoard.color | textColorFilter) === '#ffffff' }"><br /></div>
                <label class="colorselect-label{{ newBoard.color | iconWhiteFilter }} color" ng-style="getCustomBackground(newBoard.hashedColor)" ng-init="newBoard.hashedColor='#' + newBoard.color">
                    <input class="color" type="color" ng-model="newBoard.hashedColor" value="#{{newBoard.color}}" ng-change="newBoard=setHashedColor(newBoard)"/>
                </label>
			</div>
		</div>
	</li>

</ul>
