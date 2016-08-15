<div id="boardlist">
	<table width="100%">
		<thead>
		<tr>
			<td class="cell-board-bullet"></td>
			<td class="cell-board-title" width="90%"><?php p($l->t('Board title')); ?></td>
			<td class="cell-board-members"><?php p($l->t('Members')); ?></td>
		</tr>
		</thead>
		<tbody>
		<tr data-ng-repeat="b in boardservice.data"
			ui-sref="board({boardId: b.id})">
			<td>
				<span class="board-bullet"
					  style="background-color:#{{b.color}};"> </span>
			</td>
			<td><a href="#/board/{{b.id}}">{{ b.title }}</a></td>
			<td>
				<div id="assigned-users">
					<div class="avatardiv" avatar
						 displayname="{{ b.owner }}"></div>
					<div class="avatardiv" avatar
						 displayname="{{ acl.participant }}"
						 ng-repeat="acl in b.acl | limitTo: 7"></div>
				</div>
			</td>
		</tr>
		<tr>
			<td><span class="icon icon-add"></span></td>
			<td>
				<a ng-click="status.addBoard=!status.addBoard"
				   ng-show="!status.addBoard">
					<?php p($l->t('Create new board')); ?>
				</a>
				<form ng-show="status.addBoard" ng-disabled="isAddingList"
					  class="ng-pristine ng-valid" ng-submit="boardCreate()">
					<input id="newTitle" class="edit ng-valid ng-empty"
						   type="text" placeholder="<?php p($l->t('New board title')); ?>"
						   autofocus-on-insert ng-model="newBoard.title">
					<div class="colorselect">
						<div class="color" ng-repeat="c in colors"
							 style="background-color:#{{ c }};"
							 ng-click="selectColor(c)"
							 ng-class="{'selected': (c == newBoard.color) }">
							<br/></div>
					</div>
					<input type="submit" value="" class="icon-checkmark svg">
				</form>
			</td>
			<td></td>
		</tr>
		</tbody>
	</table>
</div>
