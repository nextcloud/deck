<ul class="with-icon">

	<li><a href="#" class=""><?php p($l->t('All Boards')); ?></a></li>

	<li class="with-icon with-menu" ng-class="{active: b.id === boardservice.getCurrent().id}" data-ng-repeat="b in boardservice.sorted">
		<span class="board-bullet"  style="background-color:#{{b.color}};" ng-if="!b.status.edit"> </span>
		<a href="#!/board/{{b.id}}/" ng-if="!b.status.edit">{{ b.title }}</a>
		<div class="app-navigation-entry-utils" ng-show="!b.status.edit" style="position:absolute;">
			<ul>
				<li class="app-navigation-entry-utils-counter board-delete-undo" ng-show="status.deleteUndo[b.id]" ng-click="boardDeleteUndo(b)" title="Click to undo">Deleting in {{ status.deleteUndo[b.id] }}s &nbsp; X</li>
				<li class="app-navigation-entry-utils-menu-share svg" ng-show="b.shared>0"><i class="icon icon-share" title="<?php p($l->t('Shared with you')); ?>"> </i></li>
				<li class="app-navigation-entry-utils-menu-button svg" ng-show="!status.deleteUndo[b.id]"><button class="icon-more"></button></li>
			</ul>
		</div>
		<div class="app-navigation-entry-menu app-navigation-noclose" ng-show="!b.status.edit">
			<ul>
				<li ng-show="b.owner.uid===user"><button class="icon-rename svg" title="<?php p($l->t('edit')); ?>" ng-click="b.status.edit=true"></button></li>
				<li ng-show="b.owner.uid===user"><button class="icon-delete svg" title="<?php p($l->t('delete')); ?>" ng-click="boardDelete(b)"></button></li>
				<li ng-show="b.owner.uid!==user && false"><button class="icon-delete svg" title="<?php p($l->t('remove share')); ?>" ng-click="boardRemoveShare(b)"></button></li>
			</ul>
		</div>
		<div class="app-navigation-entry-deleted" ng-show="false">
			<div class="app-navigation-entry-deleted-description">Deleted X</div>
			<button class="app-navigation-entry-deleted-button icon-history svg" title="Undo"></button>
		</div>
		<div class="app-navigation-entry-edit" ng-show="b.status.edit">
			<form ng-disabled="isAddingList" class="ng-pristine ng-valid"  ng-submit="boardUpdate(b)">
				<input id="newTitle" class="edit ng-valid ng-empty" type="text" autofocus-on-insert ng-model="b.title" maxlength="100">
				<input type="submit" value="" class="action icon-checkmark svg">
				<div class="colorselect">
					<div class="color" ng-repeat="c in colors" style="background-color:#{{ c }};" ng-click="b.color=c" ng-class="{'selected': (c == b.color) }"><br /></div>
				</div>
			</form>
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
					<div class="color" ng-repeat="c in colors" style="background-color:#{{ c }};" ng-click="selectColor(c)" ng-class="{'selected': (c == newBoard.color) }"><br /></div>
				</div>
			</form>
		</div>
	</li>

</ul>
