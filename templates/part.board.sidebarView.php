<div id="board-status" ng-if="statusservice.active">
    <div id="emptycontent">
        <div class="icon-{{ statusservice.icon }}"></div>
        <h2>{{ statusservice.title }}</h2>
        <p>{{ statusservice.text }}</p></div>
</div>
<div id="sidebar-header">
    <a class="icon-close" ui-sref="board" ng-click="sidebar.show=!sidebar.show"> &nbsp;</a>
    <h3>{{ boardservice.getCurrent().title }}</h3>
</div>

{{board=boardservice.getCurrent();""}}

<ul class="tabHeaders">
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==0 || !status.boardtab)}" ng-click="status.boardtab=0"><a><?php p($l->t('Sharing')); ?></a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==1)}" ng-click="status.boardtab=1"><a><?php p($l->t('Tags')); ?></a></li>
</ul>
<div class="tabsContainer">
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==0 || !status.boardtab">

        <ui-select ng-if="boardservice.canShare()" ng-model="status.addSharee" theme="select2" style="width:100%;" title="Choose a user to assign" placeholder="Assign users ..." on-select="aclAdd(status.addSharee)" search-enabled="true">
            <ui-select-match placeholder="<?php p($l->t('Select users or groups to share with')); ?>">
                <span><i class="icon icon-{{$item.type}}"></i> {{ $item.participant.displayname }}</span>
            </ui-select-match>
            <ui-select-choices refresh="searchForUser($select.search)" refresh-delay="0" repeat="sharee in boardservice.sharees">
                <span><i class="icon icon-{{sharee.type}}"></i> {{ sharee.participant.displayname }}</span>
            </ui-select-choices>
            <ui-select-no-choice>
            <?php p($l->t('No matching user or group found.')); ?>
            </ui-select-no-choice>
        </ui-select>

        <ul id="shareWithList" class="shareWithList">
            <li>
                <span class="icon-loading-small" style="display:none;"></span>
                <div class="avatardiv" avatar ng-attr-displayname="{{ boardservice.getCurrent().owner.uid }}" ng-if="boardservice.id"></div>
                <span class="has-tooltip username">
                    {{ boardservice.getCurrent().owner.displayname }}
				</span>
            </li>
            <li ng-repeat="acl in boardservice.getCurrent().acl track by $index">
                <span class="icon-loading-small" style="display:none;"></span>
                <div class="avatardiv" avatar displayname="{{ acl.participant.uid }}" ng-if="acl.type=='user'"></div>
                <div class="avatardiv" ng-if="acl.type=='group'"><i class="icon icon-{{acl.type}}"></i></div>

                <span class="has-tooltip username">
                    {{ acl.participant.displayname }}
				</span>
				<span class="sharingOptionsGroup">
                <span class="shareOption" ng-if="boardservice.canManage()">
                    <input type="checkbox" class="permissions checkbox" id="checkbox-permission-{{ acl.id }}-share" ng-model="acl.permissionShare" ng-change="aclUpdate(acl)" />
                    <label for="checkbox-permission-{{ acl.id }}-share"><?php p($l->t('Share')); ?></label>
                </span>
                <span class="shareOption"ng-if="boardservice.canManage()">
                    <input type="checkbox" class="permissions checkbox" id="checkbox-permission-{{ acl.id }}-edit" ng-model="acl.permissionEdit" ng-change="aclUpdate(acl)" />
                    <label for="checkbox-permission-{{ acl.id }}-edit"><?php p($l->t('Edit')); ?></label>
                </span>
                <span class="shareOption"ng-if="boardservice.canManage()">
                    <input type="checkbox" class="permissions checkbox" id="checkbox-permission-{{ acl.id }}-manage" ng-model="acl.permissionManage" ng-change="aclUpdate(acl)" />
                    <label for="checkbox-permission-{{ acl.id }}-manage"><?php p($l->t('Manage')); ?></label>
                </span>
				</span>
                <a ng-if="boardservice.canManage()" ng-click="aclDelete(acl)"><span class="icon-loading-small hidden"></span><span class="icon icon-delete"></span><span class="hidden-visually"><?php p($l->t('Discard share')); ?></span></a>
            </li>
        </ul>

    </div>
    <div id="board-detail-labels" class="tab commentsTabView" ng-if="status.boardtab==1">

            <ul class="labels">
                <li ng-repeat="label in boardservice.getCurrent().labels">
                    <span class="label-title" ng-style="{'background-color':'#{{label.color}}','color':'{{ label.color|textColorFilter }}'}" ng-if="!label.edit">
                        <span ng-if="label.title">{{ label.title }}</span><i ng-if="!label.title"><br /></i>
                    </span>
                    <div class="label-edit" ng-if="label.edit">
                        <div ng-style="{'background-color':'#{{label.color}}','color':'{{ textColor(label.color) }}','width':'100%'}>
                            <form ng-submit="labelUpdate(label)">
                                <input type="text" ng-model="label.title" ng-keyup="$event.keyCode === 13 && labelUpdate(label)" class="input-inline" ng-style="{'background-color':'#{{label.color}}','color':'{{ label.color|textColorFilter }}'}" autofocus-on-insert maxlength="100"/>
                            </form>
                        </div>
                        <div class="colorselect">
                            <div class="color" ng-repeat="c in defaultColors" ng-style="{'background-color':'#{{ c }}'}" ng-click="label.color=c" ng-class="{'selected': (c == label.color) }"><br /></div>
                        </div>
                    </div>
                    <a ng-if="boardservice.canManage() && label.edit" ng-click="labelUpdate(label)" class="icon"><i class="icon icon-checkmark" ></i></a>
                    <a ng-if="boardservice.canManage() && !label.edit" ng-click="label.edit=true" class="icon"><i class="icon icon-rename" ></i></a>
                    <a ng-if="boardservice.canManage()" ng-click="labelDelete(label)" class="icon"><i class="icon icon-delete" ></i></a>
                </li>
                <li ng-if="status.createLabel">
                    <div class="label-edit">
                        <div ng-style="{'background-color':'#{{newLabel.color}}','color':'{{ textColor(newLabel.color) }}','width':'100%'}">
                            <form ng-submit="labelCreate(newLabel)">
                                <input type="text" class="input-inline" ng-model="newLabel.title" ng-style="{'color':'{{ newLabel.color|textColorFilter }}'};" autofocus-on-insert maxlength="100" />
                            </form>
                        </div>
                        <div class="colorselect">
                            <div class="color" ng-repeat="c in defaultColors" ng-style="{'background-color':'#{{ c }}'}" ng-click="newLabel.color=c" ng-class="{'selected': (c == newLabel.color), 'dark': (newBoard.color | textColorFilter) === '#ffffff' }"><br /></div>
                        </div>
                    </div>
                    <a ng-click="labelCreate(newLabel)" class="icon"><i class="icon icon-checkmark" ></i></a>
                    <a ng-click="status.createLabel=false" class="icon"><i class="icon icon-close" ></i></a>
                </li>
                <li ng-if="boardservice.canManage() && !status.createLabel" class="label-create">
                    <a ng-click="status.createLabel=true" class="button"><span class="icon icon-add"></span><br /><span><?php p($l->t('Create a new tag')); ?></span></a>
                </li>
            </ul>

    </div>
</div>
