<div id="boardlist">
<table width="100%">
    <thead>
        <tr>
            <td class="cell-board-bullet"></td>
            <td class="cell-board-title">Board Title</td>
            <td class="cell-board-members">Members</td>
            <td class="cell-board-actions">Actions</td>

        </tr>
    </thead>
    <tbody>
        <tr data-ng-repeat="b in boardservice.data" ui-sref="board({boardId: b.id})">
            <td>
                <span class="board-bullet"  style="background-color:#{{b.color}};"> </span>
            </td>
            <td>		<a href="#/board/{{b.id}}">{{ b.title }}</a></td>
            <td>
                <div id="assigned-users">
                    <!--<div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(213, 231, 116);">D</div>//-->
                </div>

            </td>
            <td>
                    <a class="action action-share permanent" href="#" data-action="Share" data-original-title="" title=""><span class="icon icon-share"></span><span class="hidden-visually">Sharing</span></a>
                    <a class="action action-menu permanent" href="#" data-action="menu" data-original-title="" title=""><span class="icon icon-more"></span><span class="hidden-visually">Aktionen</span></a>
</td>

        </tr>
        <tr>
            <td><span class="icon icon-add"></span></td>
            <td>
                <a ng-click="status.addBoard=!status.addBoard" ng-show="!status.addBoard">
                    Board erstellen
                </a>
                <form ng-show="status.addBoard" ng-disabled="isAddingList" class="ng-pristine ng-valid"  ng-submit="createBoard()">
                    <input id="newTitle" class="edit ng-valid ng-empty" type="text" placeholder="Neue Liste" autofocus-on-insert ng-model="newBoard.title">
                    <div class="colorselect">
                        <div class="color" ng-repeat="c in colors" style="background-color:#{{ c }};" ng-click="selectColor(c)" ng-class="{'selected': (c == newBoard.color) }"><br /></div>
                    </div>
                    <input type="submit" value="" class="icon-checkmark svg">

                </form>
            </td>
            <td></td>
            <td></td>

        </tr>
    </tbody>
</table>
</div>