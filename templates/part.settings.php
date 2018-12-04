<div id="app-settings" ng-if="isAdmin">
	<div id="app-settings-header">
		<button class="settings-button" data-apps-slide-toggle="#app-settings-content"><?php p($l->t('Settings')); ?></button>
	</div>
	<div id="app-settings-content" class="hidden">
		<ui-select multiple tagging="" ng-model="groupLimit" theme="select2"
				   title="<?php p($l->t('Limit deck to groups')); ?>"
				   placeholder="<?php p($l->t('Limit deck to groups')); ?>"
				   on-select="groupLimitAdd($item, $model)"
				   on-remove="groupLimitRemove($item, $model)" ng-disabled="groupLimitDisabled">
			<ui-select-match placeholder="<?php p($l->t('Limit deck to groups')); ?>">
				<span class="select-label">{{$item.displayname}}&nbsp;</span>
			</ui-select-match>
			<ui-select-choices
					repeat="group in groups | filter: $select.search | limitTo: 3 track by group.id" position="down">
				<span class="choose-label">{{group.displayname}}</span>
			</ui-select-choices>
		</ui-select>
		<p class="hint"><?php p($l->t('Limiting Deck will block users not part of those groups from creating their own boards. Users will still be able to work on boards that have been shared with them.')); ?></p>
	</div>
</div>
