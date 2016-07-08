<div id="board-status" ng-if="statusservice.active">
    <div id="emptycontent">
        <div class="icon-{{ statusservice.icon }}"></div>
        <h2>{{ statusservice.title }}</h2>
        <p>{{ statusservice.text }}</p></div>
</div>
<div id="sidebar-header">
    <a class="icon-close" ui-sref="board" ng-click="sidebar.show=!sidebar.show"> &nbsp;</a>
    <h2>{{ boardservice.getCurrent().title }}</h2>
</div>


<ul class="tabHeaders">
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==0 || !status.boardtab)}" ng-click="status.boardtab=0"><a>Sharing</a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==1)}" ng-click="status.boardtab=1"><a>Labels</a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==2)}" ng-click="status.boardtab=2"><a>Settings</a></li>
</ul>
<div class="tabsContainer">
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==0 || !status.boardtab">

        <ui-select ng-model="status.addSharee" theme="bootstrap" style="width:100%;" title="Choose a user to assign" placeholder="Assign users ..." on-select="addSharee(status.addSharee)">
            <ui-select-match placeholder="Select users...">
                <span><i class="fa fa-{{$item.type}}"></i> {{ $item.participant }}</span>
            </ui-select-match>
            <!-- FIXME: filter by selected or add multiple //-->
            <ui-select-choices repeat="sharee in boardservice.sharees | filter: board.sharees | filter: $select.search track by $index">
                <span><i class="fa fa-{{sharee.type}}"></i> {{ sharee.participant }}</span>
            </ui-select-choices>
            <ui-select-no-choice>
                Dang!  We couldn't find any choices...
            </ui-select-no-choice>
        </ui-select>

        <ul id="shareWithList" class="shareWithList">
            <li ng-repeat="sharee in boardservice.getCurrent().acl track by $index">
                <span class="icon-loading-small" style="display:none;"></span>
                <div class="avatar " data-username="directmenu" style="height: 32px; width: 32px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 32px; font-size: 17.6px; background-color: rgb(195, 222, 124);">D</div>
                <span class="has-tooltip username">
                    <i class="fa fa-{{sharee.type}}"></i>
                    {{ sharee.participant }}</span>
                <span class="shareOption">
                    <input type="checkbox" name="edit" class="permissions checkbox" checked="checked" id=checkbox-permission-{{ $index }}">
                    <label for="checkbox-permission-{{ $index }}">teilen</label>
                </span>
                <span class="shareOption">
                    <input type="checkbox" name="edit" class="permissions checkbox" checked="checked" id=checkbox-permission-{{ $index }}">
                    <label for="checkbox-permission-{{ $index }}">bearbeiten</label>
                </span>
                <span class="shareOption">
                    <input type="checkbox" name="edit" class="permissions checkbox" checked="checked" id=checkbox-permission-{{ $index }}">
                    <label for="checkbox-permission-{{ $index }}">verwalten</label>
                </span>
                <a href="#"><span class="icon icon-delete"> </span></a>
            </li>
        </ul>

    </div>
    <div id="board-detail-labels" class="tab commentsTabView" ng-if="status.boardtab==1">

            <ul class="labels">
                <li ng-repeat="label in boardservice.getCurrent().labels">
                <span class="label-title" style="background-color:#{{label.color}}; color:{{ textColor(label.color) }};" ng-if="!label.edit" ng-click="label.edit=true">
                    <span ng-if="label.title">{{ label.title }}</span><i ng-if="!label.title"><br /></i>
                </span>
                <span class="label-title" style="background-color:#{{label.color}}; color:{{ textColor(label.color) }}; width:188px;" ng-if="label.edit">
                    <input type="text" placeholder="" ng-model="label.title" class="input-inline" style="background-color:#{{label.color}}; color:{{ textColor(label.color) }};" />
                </span>
                    <div class="colorselect" ng-if="label.edit">
                        <div class="color" ng-repeat="c in defaultColors" style="background-color:#{{ c }};" ng-click="label.color=c" ng-class="{'selected': (c == label.color) }"><br /></div>
                    </div>
                    <a class="fa fa-save" ng-click="labelUpdate(label)" ng-if="label.edit"> </a>
                    <a class="fa fa-edit" ng-click="label.edit=true" ng-if="!label.edit"> </a>
                    <a class="fa fa-remove" ng-click="labelDelete(label)"> </a>
                </li>
                <li ng-if="status.createLabel">
                    <form ng-submit="labelCreate(newLabel)">
                <span class="label-title" style="background-color:#{{newLabel.color}}; color:{{ textColor(newLabel.color) }}; width:188px;">
                    <input type="text" class="input-inline" ng-model="newLabel.title" style="color:{{ textColor(newLabel.color) }};" autofocus-on-insert />
                </span>
                        <div class="colorselect">
                            <div class="color" ng-repeat="c in defaultColors" style="background-color:#{{ c }};" ng-click="newLabel.color=c" ng-class="{'selected': (c == newLabel.color) }"><br /></div>
                        </div>
                        <a class="fa fa-save" ng-click="labelCreate(newLabel)"> </a>

                    </form>
                </li>
                <li ng-if="!status.createLabel">
                    <a ng-click="status.createLabel=true"><span class="fa fa-plus"> </span> Create a new Label</a>
                </li>
            </ul>

    </div>
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==2">
        <p><input type="checkbox" class="checkbox" id="allowInvite" /> <label for="allowInvite">Allow members to invite other users</label></p>
        <p><input type="checkbox" class="checkbox" id="allowInvite" /> <label for="allowInvite">Allow members to make board public</label></p>
        <p><input type="checkbox" class="checkbox" id="allowInvite" /> <label for="allowInvite">Allow members to change labels</label></p>
        <p><input type="checkbox" class="checkbox" id="allowInvite" /> <label for="allowInvite">Allow members to create new stacks</label></p>

    </div>
</div>
