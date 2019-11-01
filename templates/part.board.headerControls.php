<div id="stack-add" ng-if="boardservice.canManage() && checkCanEdit()">
    <form class="ng-pristine ng-valid" ng-submit="createStack()">
        <label for="new-stack-input-<?php p($_['headerControlsId']); ?>" class="hidden-visually"><?php p($l->t('Add a new stack')); ?></label>
        <input type="text" class="no-close" placeholder="<?php p($l->t('Add a new stack')); ?>"
            ng-focus="status.addStack=true"
            ng-blur="status.addStack=false"
            ng-model="newStack.title" required
            id="new-stack-input-<?php p($_['headerControlsId']); ?>"
            maxlength="100" />
        <button class="button-inline icon icon-add" ng-style="{'opacity':'{{status.addStack ? 1: 0.5}}'}" type="submit" title="<?php p($l->t('Submit')); ?>">
        	<span class="hidden-visually"><?php p($l->t('Submit')); ?></span>
        </button>
    </form>
</div>

<button ng-if="params.filter!='archive'" ng-click="switchFilter('archive')" data-toggle="tooltip" data-placement="bottom" title="<?php p($l->t('Show archived cards')); ?>">
    <i class="icon icon-archive" style="opacity:0.5;"></i>
    <span class="hidden-visually"><?php p($l->t('Show archived cards')); ?></span>
</button>
<button ng-if="params.filter=='archive'" ng-click="switchFilter('')" data-toggle="tooltip" data-placement="bottom" title="<?php p($l->t('Hide archived cards')); ?>">
    <i class="icon icon-archive"></i>
    <span class="hidden-visually"><?php p($l->t('Hide archived cards')); ?></span>
</button>
<button ng-click="toggleCompactMode()" data-toggle="tooltip" data-placement="bottom" title="<?php p($l->t('Toggle compact mode')); ?>">
	<i class="icon" ng-class="{ 'icon-toggle-compact-collapsed': compactMode, 'icon-toggle-compact-expanded': !compactMode }"></i>
	<span class="hidden-visually"><?php p($l->t('Toggle compact mode')); ?></span>
</button>
<button ui-sref="board.detail({ id: id, tab: 0})"  data-toggle="tooltip" data-placement="bottom" title="<?php p($l->t('Show board details')); ?>">
    <i class="icon icon-settings"></i>
    <span class="hidden-visually"><?php p($l->t('Show board details')); ?></span>
</button>
