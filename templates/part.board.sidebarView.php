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
        <input class="shareWithField ui-autocomplete-input" type="text" placeholder="Mit Benutzern, Gruppen oder entfernten Benutzern teilen…" autocomplete="off">
        <ul id="shareWithList" class="shareWithList">
            <li data-share-id="57" data-share-type="0" data-share-with="directmenu">
                <a href="#" class="unshare"><span class="icon-loading-small"></span><span class="icon icon-delete"><br /></span><span class="hidden-visually">Freigabe aufheben</span></a>
                <div class="avatar " data-username="directmenu" style="height: 32px; width: 32px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 32px; font-size: 17.6px; background-color: rgb(195, 222, 124);">D</div>
                <span class="has-tooltip username" title="" data-original-title="directmenu" aria-describedby="tooltip777914">directmenu</span>
                <span class="shareOption">
                    <input id="canShare-view17-directmenu" type="checkbox" name="share" class="permissions checkbox" checked="checked" data-permissions="16">
                    <label for="canShare-view17-directmenu">kann teilen</label>
                </span>
                <span class="shareOption"><input id="canEdit-view17-directmenu" type="checkbox" name="edit" class="permissions checkbox" checked="checked">
                    <label for="canEdit-view17-directmenu">kann bearbeiten</label>
                </span>
            </li>
        </ul>

    </div>
    <div id="board-detail-labels" class="tab commentsTabView" ng-if="status.boardtab==1">

            <ul class="labels">
                <li ng-repeat="label in boardservice.getCurrent().labels">
                <span class="label-title" style="background-color:#{{label.color}}; color:{{ textColor(label.color) }};" ng-if="!label.edit">
                    {{ label.title }}
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
