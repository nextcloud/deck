<div id="board-status" ng-if="statusservice.active">
	<div id="emptycontent">
		<div class="icon-{{ statusservice.icon }}"></div>
		<h2>{{ statusservice.title }}</h2>
		<p>{{ statusservice.text }}</p></div>
</div>
{{card=cardservice.getCurrent();""}}
<div id="card-header">
	<a class="icon-close" ui-sref="board" ng-click="sidebar.show=!sidebar.show">&nbsp;</a>
	<h2>
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
	</h2>
</div>

<div id="card-meta" class="card-block">
	<div id="card-dates">
		<?php p($l->t('Modified:')); ?> <span class="live-relative-timestamp" data-timestamp="{{cardservice.getCurrent().lastModified*1000}}">{{ cardservice.getCurrent().lastModified|relativeDateFilter }}</span>
		<?php p($l->t('Created:')); ?> <span class="live-relative-timestamp" data-timestamp="{{cardservice.getCurrent().createdAt*1000}}">{{ cardservice.getCurrent().createdAt|relativeDateFilter }}</span>
		<?php p($l->t('by')); ?>
		<span>{{ cardservice.getCurrent().owner.displayname }}</span>
	</div>
	<h3 id='card-tag-label'>
		<?php p($l->t('Tags')); ?>
	</h3>
	<div id="labels">
		<ui-select multiple tagging="" ng-model="card.labels" theme="select2"
				   ng-disabled="boardservice.isArchived() || card.archived"
				   style="width:100%;" title="<?php p($l->t('Choose a label')); ?>"
				   placeholder="<?php p($l->t('Add a label')); ?>"
				   on-select="labelAssign($item, $model)"
				   on-remove="labelRemove($item, $model)" ng-disabled="!boardservice.canEdit() || archived">
			<ui-select-match placeholder="<?php p($l->t('Select labels…')); ?>">
				<span class="select-label" style="background-color:#{{$item.color}}; color:{{ $item.color|textColorFilter }};">{{$item.title}}&nbsp;</span>
			</ui-select-match>
			<ui-select-choices
				repeat="label in boardservice.getCurrent().labels | filter:$select.search">
				<span class="choose-label" style="background-color:#{{label.color}}; color:{{ label.color|textColorFilter }};">{{label.title}}</span>
			</ui-select-choices>
		</ui-select>
	</div>
	<h3>
		<?php p($l->t('Due date')); ?>
	</h3>
	<div class="duedate">
		<input class="datepicker-input medium focus" type="text" placeholder="<?php p($l->t('Click to set')); ?>" value="{{ cardservice.getCurrent().duedate | parseDate }}" datepicker="due" />
		<input class="timepicker-input medium focus" type="text" placeholder="00:00:00" ng-if="cardservice.getCurrent().duedate" value="{{ cardservice.getCurrent().duedate | parseTime }}" timepicker="due" />
		<button class="icon icon-delete button-inline" title="<?php p($l->t('Remove due date')); ?>" ng-if="cardservice.getCurrent().duedate" ng-click="resetDuedate()"></button>
	</div>

	
	<div id="card-description">
		<h3>
			<div>
				<div>
					<?php p($l->t('Description')); ?>
					<a href="https://github.com/nextcloud/deck/wiki/Markdown-Help" target="_blank" class="icon-help" title="<?php p($l->t('Formatting help')); ?>"></a>
				</div>
				<span class="save-indicator"><?php p($l->t('Saved')); ?></span>
			</div>
			
		</h3>
		<textarea elastic ng-if="status.cardEditDescription"
				  placeholder="Enter your description here…"
				  ng-blur="cardUpdate(cardservice.getCurrent())"
				  ng-model="cardservice.getCurrent().description"
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