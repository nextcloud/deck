<ul class="with-icon">

	<li ng-class="{active: status.filter === '' && !boardservice.getCurrent()}"><a ui-sref="list({ filter: ''})" class="icon-deck"><?php p($l->t('All Boards')); ?></a></li>
	<li ng-class="{active: status.filter === 'archived' || (boardservice.getCurrent() && boardservice.getCurrent().archived)}"><a ui-sref="list({ filter: 'archived' })" class="icon-archive"><?php p($l->t('Archived boards')); ?></a></li>
	<li ng-class="{active: status.filter === 'shared'}"><a ui-sref="list({ filter: 'shared' })" class="icon-share"><?php p($l->t('Shared boards')); ?></a></li>

	<li class="with-icon with-menu" ng-class="{active: b.id === boardservice.getCurrent().id}" data-ng-repeat="b in boardservice.sidebar" ng-if="b.deletedAt == 0">
		<span class="board-bullet"  ng-style="{'background-color':'#{{b.color}}'}" ng-if="!b.status.edit"> </span>
		<a href="#!/board/{{b.id}}/" ng-if="!b.status.edit">{{ b.title }}</a>
		<div class="app-navigation-entry-utils" ng-show="!b.status.edit" style="position:absolute;">
			<ul>
				<li class="app-navigation-entry-utils-menu-share svg" ng-if="b.shared>0"><i class="icon icon-share" title="<?php p($l->t('Shared with you')); ?>"> </i></li>
				<li class="app-navigation-entry-utils-menu-button svg" ng-show="!status.deleteUndo[b.id]"><button class="icon-more"></button></li>
			</ul>
		</div>
		<div class="app-navigation-entry-menu app-navigation-noclose" ng-show="!b.status.edit">
			<ul>
				<li ng-show="boardservice.canManage(b)"><button class="icon-rename svg" title="<?php p($l->t('Edit board')); ?>" ng-click="b.status.edit=true"></button></li>
				<li ng-show="boardservice.canManage(b)"><button class="icon-archive svg" title="<?php p($l->t('Move board to archive')); ?>" ng-click="boardArchive(b)"></button></li>
			</ul>
		</div>
		<div class="app-navigation-entry-edit" ng-show="b.status.edit">
			<form ng-disabled="isAddingList" class="ng-pristine ng-valid"  ng-submit="boardUpdate(b)">
				<input id="newTitle" class="edit ng-valid ng-empty" type="text" autofocus-on-insert ng-model="b.title" maxlength="100">
				<input type="submit" value="" class="action icon-checkmark svg">
			</form>
			<div class="colorselect">
				<div class="color" ng-repeat="c in colors" ng-style="{'background-color':'#{{ c }}'}" ng-click="b.color=c" ng-class="{'selected': (c == b.color) }"></div>
			</div>
		</div>
	</li>

	<li>
		<a ng-click="status.addBoard=!status.addBoard" ng-show="!status.addBoard" class="icon-add app-navigation-noclose">
			<?php p($l->t('Create a new board')); ?>
		</a>
		<div class="app-navigation-entry-edit" ng-if="status.addBoard">
			<form ng-disabled="isAddingList" class="ng-pristine ng-valid"  ng-submit="boardCreate()">
				<input id="newTitle" class="edit ng-valid ng-empty" type="text" placeholder="<?php p($l->t('New board title')); ?>" autofocus-on-insert ng-model="newBoard.title" maxlength="100">
				<input type="submit" value="" class="action icon-checkmark svg">
				<div class="colorselect">
					<div class="color" ng-repeat="c in colors" ng-style="{'background-color':'#{{ c }}'}" ng-click="selectColor(c)" ng-class="{'selected': (c == newBoard.color), 'dark': (newBoard.color | textColorFilter) === '#ffffff' }"><br /></div>
				</div>
			</form>
		</div>
	</li>

</ul>
