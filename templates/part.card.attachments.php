<div ng-class="{'attachment-list-wrapper': $ctrl.isFileSelector}">
<div class="attachment-list" ng-class="{selector: $ctrl.isFileSelector}">
	<h3 class="attachment-selector" ng-if="$ctrl.isFileSelector"><?php p($l->t('Select an attachment')); ?>	<a class="icon-close" ng-click="$ctrl.abort()"></a></h3>
<ul>
	<li class="attachment"
		ng-repeat="attachment in $ctrl.fileservice.getProgressItemsForCard($ctrl.cardservice.getCurrent().id)">
		<a class="fileicon icon-file"></a>
		<div class="details">
			<div class="filename">
				<span class="basename">{{ attachment.file.name }}</span>
			</div>
			<progress ng-attr-value="{{ attachment.progress }}" max="100"></progress>
		</div>
		<button class="icon icon-close button-inline" ng-click="attachment.cancel()">
			<span class="hidden-visually"><?php p($l->t('Cancel upload')); ?></span>
		</button>
	</li>
	<li class="attachment"
		ng-repeat="attachment in $ctrl.cardservice.getCurrent().attachments | filter: {type: 'deck_file'} | orderBy: ['deletedAt', '-lastModified']"
		ng-class="{deleted: attachment.deletedAt > 0, selector: $ctrl.isFileSelector}"
		ng-if="!$ctrl.isFileSelector || attachment.deletedAt == 0">
		<a class="fileicon" ng-style="$ctrl.mimetypeForAttachment(attachment)" ng-href="{{ attachmentUrl(attachment) }}"></a>
			<div class="details">
				<a ng-href="{{ $ctrl.attachmentUrl(attachment) }}" target="_blank">
				<div class="filename">
					<span class="basename">{{ attachment.extendedData.info.filename}}</span>
					<span class="extension">.{{ attachment.extendedData.info.extension}}</span>
				</div>
					<span class="filesize">{{ attachment.extendedData.filesize | bytes }}</span>
					<span class="filedate">{{ attachment.lastModified|relativeDateFilter }}</span>
					<span class="filedate"><?php p($l->t('by')); ?> {{ attachment.createdBy }}</span>
				</a>
			</div>
			<button class="icon icon-history button-inline" ng-click="$ctrl.cardservice.attachmentRemoveUndo(attachment)" ng-if="!$ctrl.isFileSelector && attachment.deletedAt > 0" title="<?php p($l->t('Undo file deletion - Otherwise the file will be deleted during the next cronjob run.')); ?>">
				<span class="hidden-visually"><?php p($l->t('Undo file deletion')); ?></span>
			</button>
			<button class="icon icon-confirm button-inline" ng-click="$ctrl.select(attachment)" ng-if="$ctrl.isFileSelector">
				<span class="hidden-visually"><?php p($l->t('Insert the file into the description')); ?></span>
			</button>
			<div class="app-popover-menu-utils" ng-if="!$ctrl.isFileSelector && attachment.deletedAt == 0">
				<button class="button-inline icon icon-more" title="<?php p($l->t('Actions')) ?>"></button>
				<div class="popovermenu hidden">
					<ul>
						<li>
							<a class="menuitem action action-delete"
							   ng-click="$ctrl.cardservice.attachmentRemove(attachment); $event.stopPropagation();"><span
										class="icon icon-delete"></span><span><?php p($l->t('Delete attachment')); ?></span></a>
						</li>
					</ul>
				</div>
			</div>
		</a>
	</li>
</ul>
</div>