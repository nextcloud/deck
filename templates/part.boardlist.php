<div id="board-status" ng-if="statusservice.active">
	<div id="emptycontent">
		<div class="icon-{{ statusservice.icon }}" title="<?php p($l->t('Status')); ?>"><span class="hidden-visually"><?php p($l->t('Status')); ?></span></div>
		<h2>{{ statusservice.title }}</h2>
		<p>{{ statusservice.text }}</p></div>
</div>
<div id="controls">
	<div class="breadcrumb">
		<div class="crumb svg last">
			<a href="#" class="icon-home" title="<?php p($l->t('All Boards')); ?>">
				<span class="hidden-visually"><?php p($l->t('All Boards')); ?></span>
			</a>
		</div>
	</div>
</div>
<div id="emptycontent" ng-if="boardservice.sorted.length == 0 && status.filter == 'archived'">
	<div class="icon-archive"></div>
	<h2><?php p($l->t('No archived boards to display')); ?></h2>
</div>
<div id="emptycontent" ng-if="boardservice.sorted.length == 0 && status.filter == 'shared'">
	<div class="icon-share"></div>
	<h2> <?php p($l->t('No shared boards to display')); ?> </h2>
</div>
<div id="boardlist" ng-if="boardservice.sorted.length > 0 || !status.filter">
	<table width="100%">
		<thead>
		<tr>
			<td class="cell-board-bullet"></td>
			<td class="cell-board-title" width="90%"><?php p($l->t('Title')); ?></td>
			<td class="cell-board-members"><?php p($l->t('Members')); ?></td>
			<td></td>
		</tr>
		</thead>
		<tbody>
		<tr data-ng-repeat="b in boardservice.sorted track by b.id" ng-class="{deleted: b.deletedAt > 0}">
			<td ng-click="gotoBoard(b)">
				<div ng-if="b.acl.length !== 0" class="board-bullet-shared{{ b.color | iconWhiteFilter }}" ng-style="{'background-color':'#'+b.color}"></div>
				<div ng-if="b.acl.length === 0" class="board-bullet" ng-style="{'background-color':'#'+b.color}"></div>
			</td>
			<td>
				<div ng-click="gotoBoard(b)" ng-show="!b.status.edit">{{ b.title }}</div>
				<div class="app-navigation-entry-edit" ng-show="b.status.edit">
					<form ng-disabled="isAddingList" class="ng-pristine ng-valid" ng-submit="boardUpdate(b)">
						<input class="edit ng-valid ng-empty" type="text" autofocus-on-insert ng-model="b.title" maxlength="100" ng-model-options="{ debounce: 250 }">
						<div class="colorselect" ng-controller="ColorPickerController">
							<div class="color" ng-repeat="c in ::colors" ng-style="{'background-color':'#{{ c }}'}" ng-click="b=setColor(b,c)" ng-class="{'selected': (c == b.color) }"></div>
							<label class="colorselect-label{{ b.color | iconWhiteFilter }} color" ng-style="getCustomBackground(b.hashedColor)" ng-init="b.hashedColor='#' + b.color">
								<input class="color" type="color" ng-model="b.hashedColor" value="#{{b.color}}" ng-change="b=setHashedColor(b)"/>
							</label>
						</div>
					</form>
				</div>
			</td>
			<td>
				<div id="assigned-users">
					<avatar data-contactsmenu data-tooltip data-user="{{ b.owner.uid }}" data-displayname="{{ b.owner.displayname }}"></avatar>
					<avatar data-contactsmenu data-tooltip data-user="{{ acl.participant.uid }}" data-displayname="{{ acl.participant.displayname }}" ng-repeat="acl in b.acl | limitTo: 7 track by acl.id"></avatar>
				</div>
			</td>
			<td>
				<div class="app-popover-menu-utils" ng-if="b.deletedAt == 0" ng-show="!b.status.edit">
					<button class="icon icon-more button-inline" title="<?php p($l->t('More actions')); ?>">
						<span class="hidden-visually"><?php p($l->t('More actions')); ?></span>
					</button>
					<div class="popovermenu bubble hidden">
						<ul>
							<li ng-click="boardUpdateBegin(b); b.status.edit = true">
								<a class="menuitem"><span class="icon-rename"></span> <?php p($l->t('Edit board')); ?>
								</a>
							</li>
							<li ng-if="boardservice.canManage(b) && !b.archived" ng-click="boardArchive(b)">
								<a class="menuitem"><span class="icon-archive"></span> <?php p($l->t('Archive board')); ?>
								</a>
							</li>
							<li ng-if="boardservice.canManage(b) && b.archived" ng-click="boardUnarchive(b)">
								<a class="menuitem"><span class="icon-archive"></span> <?php p($l->t('Unarchive board')); ?>
								</a>
							</li>
							<li ng-if="boardservice.canManage(b)" ng-click="boardDelete(b)">
								<a class="menuitem"><span class="icon-delete"></span> <?php p($l->t('Delete board')); ?>
								</a>
							</li>
							<li ui-sref="board.detail({boardId: b.id})">
								<a class="menuitem"><span class="icon-settings-dark"></span> <?php p($l->t('Board details')); ?>
								</a>
							</li>
						</ul>
					</div>
				</div>
				<div class="board-edit-controls" ng-show="b.status.edit">
					<span class="icon icon-checkmark" ng-click="boardUpdate(b)" title="<?php p($l->t('Update')); ?>"><span class="hidden-visually"><?php p($l->t('Update')); ?></span></span>
					<span class="icon icon-close" ng-click="boardUpdateReset(b)" title="<?php p($l->t('Reset')); ?>"><span class="hidden-visually"><?php p($l->t('Reset')); ?></span></span>
				</div>
				<div class="app-popover-menu-utils" ng-if="b.deletedAt > 0">
					<button class="icon icon-history button-inline" ng-click="boardDeleteUndo(b)" title="<?php p($l->t('Undo board deletion - Otherwise the board will be deleted during the next cronjob run.')); ?>"><span class="hidden-visually"><?php p($l->t('Undo board deletion - Otherwise the board will be deleted during the next cronjob run.')); ?></span></button>
				</div>
			</td>
		</tr>
		<tr ng-if="status.filter === '' && !status.addBoard" ng-click="status.addBoard=!status.addBoard" class="board-create">
			<td><span class="icon icon-add"></span></td>
			<td colspan="3">
				<a ng-click="status.addBoard=!status.addBoard"
				   ng-show="!status.addBoard">
					<?php p($l->t('Create new board')); ?>
				</a>
			</td>
		</tr>

		<tr ng-if="status.filter === '' && status.addBoard">
			<td><span class="icon icon-add"></span></td>
			<td>
				<form ng-disabled="isAddingList"
					  class="ng-pristine ng-valid" ng-submit="boardCreate()">
					<input class="edit ng-valid ng-empty"
						   type="text" placeholder="<?php p($l->t('New board title')); ?>"
						   autofocus-on-insert ng-model="newBoard.title" maxlength="100" ng-model-options="{ debounce: 250 }">
						<div class="colorselect" ng-controller="ColorPickerController">
						<div class="color" ng-repeat="c in ::colors" ng-style="{'background-color':'#{{ c }}'}" ng-click="selectColor(c);b=setColor(b,c);"ng-class="{'selected': (c == newBoard.color), 'dark': (newBoard.color | textColorFilter) === '#ffffff' }"></div>
							<label class="colorselect-label{{ newBoard.color | iconWhiteFilter }} color" ng-style="getCustomBackground(newBoard.hashedColor)" ng-init="newBoard.hashedColor='#' + newBoard.color">
							<input class="color" type="color" ng-model="newBoard.hashedColor" value="#{{newBoard.color}}" ng-change="newBoard=setHashedColor(newBoard)"/>
						</label>
						</div>
				</form>
			</td>
			<td></td>
			<td>
				<div class="board-edit-controls">
					<span class="icon icon-checkmark" ng-click="boardCreate()" title="<?php p($l->t('Create')); ?>"><span class="hidden-visually"><?php p($l->t('Create')); ?></span></span>
					<span class="icon icon-close" ng-click="status.addBoard=!status.addBoard" title="<?php p($l->t('Close')); ?>"><span class="hidden-visually"><?php p($l->t('Close')); ?></span></span>
				</div>
			</td>
		</tr>
		</tbody>
	</table>
</div>
