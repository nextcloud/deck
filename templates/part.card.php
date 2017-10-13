<div id="board-status" ng-if="statusservice.active">
	<div id="emptycontent">
		<div class="icon-{{ statusservice.icon }}" title="<?php p($l->t('Status')); ?>"><span class="hidden-visually"><?php p($l->t('Status')); ?></span></div>
		<h2>{{ statusservice.title }}</h2>
		<p>{{ statusservice.text }}</p></div>
</div>
{{card=cardservice.getCurrent();""}}
<div id="sidebar-header">
	<a class="icon-close" ui-sref="board" ng-click="sidebar.show=!sidebar.show">&nbsp;</a>
	<h3>
		<!-- TODO: change to textarea elastic //-->
		<form ng-submit="cardRename(cardservice.getCurrent())">
			<input class="input-inline" type="text" ng-if="status.cardRename"
				   ng-model="cardservice.getCurrent().title"
				   ng-blur="cardRename(cardservice.getCurrent())"
				   autofocus-on-insert required maxlength="100">
		</form>
		<div ng-click="cardRenameShow()" ng-show="!status.cardRename">
			{{ cardservice.getCurrent().title }}
		</div>
	</h3>
</div>

<div id="card-meta" class="card-block">
	<div id="card-dates">
		<?php p($l->t('Modified:')); ?> <span class="live-relative-timestamp" data-timestamp="{{cardservice.getCurrent().lastModified*1000}}">{{ cardservice.getCurrent().lastModified|relativeDateFilter }}</span>
		<?php p($l->t('Created:')); ?> <span class="live-relative-timestamp" data-timestamp="{{cardservice.getCurrent().createdAt*1000}}">{{ cardservice.getCurrent().createdAt|relativeDateFilter }}</span>
		<?php p($l->t('by')); ?>
		<span>{{ cardservice.getCurrent().owner.displayname }}</span>
	</div>
	<div class="section-header">
		<h4 id="card-tag-label" ng-show="!(boardservice.isArchived() || card.archived) && card.labels">
			<?php p($l->t('Tags')); ?>
		</h4>
	</div>
	<div id="labels" ng-show="!(boardservice.isArchived() || card.archived) && card.labels">
		<ui-select multiple tagging="" ng-model="card.labels" theme="select2"
				   ng-disabled="boardservice.isArchived() || card.archived"
				   title="<?php p($l->t('Choose a tag')); ?>"
				   placeholder="<?php p($l->t('Add a tag')); ?>"
				   on-select="labelAssign($item, $model)"
				   on-remove="labelRemove($item, $model)" ng-disabled="!boardservice.canEdit() || archived">
			<ui-select-match placeholder="<?php p($l->t('Select tags')); ?>">
				<span class="select-label" ng-style="{'background-color':'#{{$item.color}}','color':'{{ $item.color|textColorFilter }}'}">{{$item.title}}&nbsp;</span>
			</ui-select-match>
			<ui-select-choices
				repeat="label in boardservice.getCurrent().labels | filter:$select.search">
				<span class="choose-label" ng-style="{'background-color':'#{{label.color}}','color':'{{ label.color|textColorFilter }}'}">{{label.title}}</span>
			</ui-select-choices>
		</ui-select>
	</div>
	<div class="section-header">
		<h4><?php p($l->t('Assign users')); ?></h4>
		<button class="button icon-add" ng-click="showAssignUser()"></button>
	</div>
	<div class="section-content card-details-assign-users">
		<ui-select id="assignUserSelect" class="card-details-assign-user" ng-model="status.assignedUser" ng-show="status.showAssignUser" uis-open-close="assingUserOpenClose(isOpen)"
				   theme="select2"
				   title="Choose a user to assign" placeholder="Choose a user to assign"
				   on-select="addAssignedUser($item)">
			<ui-select-match placeholder="<?php p($l->t('Assign this card to a user')); ?>">
				<span><i class="icon icon-{{$item.type}}"></i> {{ $item.participant.displayname }}</span>
			</ui-select-match>
			<ui-select-choices repeat="user in boardservice.getCurrent().users | filter: $select.search | withoutAssignedUsers: cardservice.getCurrent().assignedUsers track by user.uid">
				<div class="avatardiv" avatar ng-attr-displayname="{{ user.uid }}" ng-if="boardservice.id"></div><span>{{ user.displayname }}</span>
			</ui-select-choices>
	</ui-select>
		<div class="card-details-assign-users-list">
			<div class="assigned-user" ng-repeat="user in cardservice.getCurrent().assignedUsers">
				<div class="avatardiv" avatar ng-attr-displayname="{{ user.participant.uid }}"></div>
				<div class="icon icon-delete" ng-click="removeAssignedUser(user)"></div>
			</div>
		</div>
	</div>

	<div class="section-header">
		<h4>
			<?php p($l->t('Due date')); ?>
		</h4>
	</div>
	<div class="duedate">
		<input class="datepicker-input medium focus" type="text" placeholder="<?php p($l->t('Click to set')); ?>" value="{{ cardservice.getCurrent().duedate | parseDate }}" datepicker="due" ng-disabled="(boardservice.isArchived() || card.archived)" />
		<input class="timepicker-input medium focus" type="text" placeholder="00:00" ng-disabled="!cardservice.getCurrent().duedate || (boardservice.isArchived() || card.archived)" value="{{ cardservice.getCurrent().duedate | parseTime }}" timepicker="due" />
		<button class="icon icon-delete button-inline" title="<?php p($l->t('Remove due date')); ?>" ng-if="cardservice.getCurrent().duedate" ng-click="resetDuedate()"><span class="hidden-visually"><?php p($l->t('Remove due date')); ?></span></button>
	</div>

	<div class="section-header">
		<h4>
			<div>
				<?php p($l->t('Description')); ?>
				<a href="https://github.com/nextcloud/deck/wiki/Markdown-Help" target="_blank" class="icon icon-help" title="<?php p($l->t('Formatting help')); ?>"><span class="hidden-visually"><?php p($l->t('Formatting help')); ?></span></a>
			</div>
			<span class="save-indicator saved"><?php p($l->t('Saved')); ?></span>
			<span class="save-indicator unsaved"><?php p($l->t('Unsaved changes')); ?></span>
		</h4>
	</div>
	<div id="card-description">
		<textarea elastic ng-if="status.cardEditDescription"
				  placeholder="<?php p($l->t('Add a card description…')); ?>"
				  ng-blur="cardUpdate(cardservice.getCurrent())"
				  ng-model="cardservice.getCurrent().description"
				  ng-change="cardEditDescriptionChanged()"
				  autofocus-on-insert> </textarea>
		<div class="container" ng-click="cardEditDescriptionShow($event)"
			 ng-if="!status.cardEditDescription" ng-animate>
			<div markdown-it="cardservice.getCurrent().description"
				 id="markdown"></div>
			<div class="placeholder"
				 ng-if="!cardservice.getCurrent().description"><?php p($l->t('Add a card description…')); ?></div>
		</div>
	</div>
</div>
