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

	<div id="labels">
		<ui-select multiple tagging="" ng-model="card.labels" theme="select2"
				   ng-disabled="boardservice.isArchived() || card.archived"
				   style="width:100%;" title="Choose a label"
				   placeholder="Add a label"
				   on-select="labelAssign($item, $model)"
				   on-remove="labelRemove($item, $model)" ng-disabled="!boardservice.canEdit() || archived">
			<ui-select-match placeholder="Select labels…">
				<span class="select-label" style="background-color:#{{$item.color}}; color:{{ $item.color|textColorFilter }};">{{$item.title}}&nbsp;</span>
			</ui-select-match>
			<ui-select-choices
				repeat="label in boardservice.getCurrent().labels | filter:$select.search">
				<span
					style="background-color:#{{label.color}}; color:{{ label.color|textColorFilter }};">{{label.title}}</span>
			</ui-select-choices>
		</ui-select>

		<div class="duedate">
			<input class="datepicker-input medium focus" type="text" placeholder="Set a due date" value="{{ cardservice.getCurrent().duedate | parseDate }}" datepicker="due" />
			<input class="timepicker-input medium focus" type="text" placeholder="00:00:00" ng-if="cardservice.getCurrent().duedate" value="{{ cardservice.getCurrent().duedate | parseTime }}" timepicker="due" />
			<button class="icon icon-delete button-inline" title="<?php p($l->t('Remove due date')); ?>" ng-if="cardservice.getCurrent().duedate" ng-click="resetDuedate()"></button>
		</div>

	</div>

	<!--<div id="assigned-users">
		<ui-select multiple tagging="" ng-model="card.assignees"
				   theme="bootstrap" style="width:100%;"
				   title="Choose a user to assign"
				   placeholder="Assign users…"
				   on-select="userAssign($item, $model)"
				   on-remove="userRemove($item, $model)" ng-disabled="archived">
			<ui-select-match placeholder="Select users…">{{$item.title}}
			</ui-select-match>
			<ui-select-choices
				repeat="label in boardservice.getCurrent().labels | filter:$select.search">
				<span
					style="background-color:#{{label.color}}">{{label.title}}</span>
			</ui-select-choices>
		</ui-select>
	</div>//-->

	<div id="card-description">
		<h3>
			<?php p($l->t('Description')); ?>
			<a href="https://github.com/nextcloud/deck/wiki/Markdown-Help" target="_blank" class="icon-help" title="<?php p($l->t('Formatting help')); ?>"></a>
			<span class="save-indicator"><?php p($l->t('Saved')); ?></span>
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

<!--
<ul class="tabHeaders">
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==0 || !status.boardtab)}" ng-click="status.boardtab=0"><a><?php p($l->t('Attachments')); ?></a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==1)}" ng-click="status.boardtab=1"><a><?php p($l->t('Comments')); ?></a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==2)}" ng-click="status.boardtab=2"><a><?php p($l->t('History')); ?></a></li>
</ul>
<div class="tabsContainer">
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==0 || !status.boardtab">
        <div id="card-attachments">
            <button ng-click="status.addAttachment=true"><i class="fa fa-plus"></i> Add an attachment</button>
            <div ng-if="status.addAttachment" id="attachment-add">
            <button><i class="fa fa-file"></i> Attach a File</button>
            <button><i class="fa fa-link"></i> Attach a URL</button>
            </div>
            <ul>
                <li>
                    <a href="#">
                        <span class="fa fa-file"></span> myfilename.pdf
                        <div class="details">
                            <span class="user">Added by John Doe at</span>
                            <span class="added">1.3.2014 14:13</span>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div id="board-detail-labels" class="tab commentsTabView" ng-if="status.boardtab==1">
    </div>
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==2">
    </div>
</div>

//-->
