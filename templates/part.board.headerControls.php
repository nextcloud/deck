<div id="stack-add" ng-if="boardservice.canEdit() && checkCanEdit()">
    <form class="ng-pristine ng-valid" ng-submit="createStack()">
        <input type="text" class="no-close" placeholder="<?php p($l->t('Add a new stack')); ?>"
            ng-focus="status.addStack=true"
            ng-blur="status.addStack=false"
            ng-model="newStack.title" required
            maxlength="100" />
        <button class="button-inline icon icon-add" ng-style="{'opacity':'{{status.addStack ? 1: 0.5}}'}" type="submit"></button>
    </form>
</div>

<a class="button" ng-if="filter!='archive'" ng-click="switchFilter('archive')" style="opacity:0.5;" title="<?php p($l->t('Show archived cards')); ?>">
    <i class="icon icon-archive"></i>
</a>
<a class="button" ng-if="filter=='archive'" ng-click="switchFilter('')" title="<?php p($l->t('Hide archived cards')); ?>">
    <i class="icon icon-archive"></i>
</a>
<a class="button" ui-sref="board.detail({ id: id })"  title="<?php p($l->t('Board details')); ?>">
    <i class="icon icon-details"></i>
</a>
