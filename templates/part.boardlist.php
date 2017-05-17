<div id="boardlist">
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
		<tr data-ng-repeat="b in boardservice.sorted">
			<td ui-sref="board({boardId: b.id})">
				<span class="board-bullet"
					  style="background-color:#{{b.color}};"> </span>
			</td>
			<td ui-sref="board({boardId: b.id})"><a href="#/board/{{b.id}}">{{ b.title }}</a></td>
			<td>
				<div id="assigned-users">
					<div class="avatardiv" avatar displayname="{{ b.owner.uid }}" title="{{ b.owner.displayname }}"></div>
					<div class="avatardiv" avatar displayname="{{ acl.participant.uid }}" title="{{ acl.participant.uid }}" ng-repeat="acl in b.acl | limitTo: 7"></div>
				</div>
			</td>
			<td>
				<div class="app-popover-menu-utils">
					<button class="icon icon-more"></button>
					<div class="popovermenu bubble hidden">
						<ul>
							<li ng-if="boardservice.canManage(b) && !b.archived" ng-click="boardArchive(b)">
								<a class="menuitem"><span class="icon-archive"></span> <?php p($l->t('Archive board')); ?>
								</a>
							</li>
							<li ng-if="boardservice.canManage(b) && b.archived" ng-click="boardUnarchive(b)">
								<a class="menuitem"><span class="icon-archive"></span> <?php p($l->t('Unarchive board')); ?>
								</a>
							</li>
							<li ng-if="boardservice.canManage(b) && b.archived" ng-click="boardDelete(b)">
								<a class="menuitem"><span class="icon-delete"></span> <?php p($l->t('Delete board')); ?>
								</a>
							</li>
							<li ui-sref="board.detail({boardId: b.id})">
								<a class="menuitem"><span class="icon-info"></span> <?php p($l->t('Board details')); ?>
								</a>
							</li>
						</ul>
					</div>
				</div>
			</td>
		</tr>
		<tr ng-if="status.filter === '' && !status.addBoard" ng-click="status.addBoard=!status.addBoard">
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
					<input id="newTitle" class="edit ng-valid ng-empty"
						   type="text" placeholder="<?php p($l->t('New board title')); ?>"
						   autofocus-on-insert ng-model="newBoard.title" maxlength="100">
					<div class="colorselect">
						<div class="color" ng-repeat="c in colors"
							 style="background-color:#{{ c }};"
							 ng-click="selectColor(c)"
							 ng-class="{'selected': (c == newBoard.color), 'dark': (newBoard.color | textColorFilter) === '#ffffff' }"></div>
					</div>
					<input type="submit" value="" class="icon-checkmark svg" />
				</form>
			</td>
			<td></td>
			<td></td>
		</tr>
		</tbody>
	</table>
</div>
