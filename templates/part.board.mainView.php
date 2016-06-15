<div id="board-status" ng-if="statusservice.active">
    <div id="emptycontent">
        <div class="icon-{{ statusservice.icon }}"></div>
        <h2>{{ statusservice.title }}</h2>
        <p>{{ statusservice.text }}</p></div>
</div>
<div id="board" class="scroll-container" >
    <h1 style="border-bottom: 1px solid {{rgblight(boardservice.getCurrent().color)}};">
        {{ boardservice.data[id].title }}
    </h1>
    <?php /* maybe later
    <div class="board-actions">
      <button class="fa fa-share-alt"></button>
      <button class="fa fa-users"></button>
      <button class="fa fa-ellipsis-h"></button>
    </div> */ ?>

    <div id="innerBoard" data-ng-model="stacks">
    <div class="stack" ng-repeat="s in stackservice.data" data-columnindex="{{$index}}" id="column{{$index}}" data-ng-model="stackservice.data"  style="border: 3px solid #{{ boardservice.getCurrent().color }};">
      <h2><span ng-show="!s.status.editStack">{{ s.title }}</span>
          <form ng-submit="stackservice.update(s)">
          <input type="text" placeholder="Add a new stack" ng-blur="s.status.editStack=false" ng-model="s.title" ng-if="s.status.editStack" autofocus-on-insert/>
          <button class="icon icon-save" ng-if="s.status.editStack" type="submit"></button>
          </form>
          <div class="stack-actions">
             <button class="icon-rename" ng-click="s.status.editStack=true"></button>
              <button class="icon-delete" ng-click="stackservice.delete(s.id)"></button>
          </div>
      </h2>
        <ul data-as-sortable="sortOptions" data-ng-model="s.cards"  style="min-height: 40px;">
      <li class="card as-sortable-item" ng-repeat="c in s.cards" data-as-sortable-item>
        <a href="#/board/{{ id }}/card/{{ c.id }}" data-as-sortable-item-handle>
        <h3><!--<i class="fa fa-github"></i>//--> {{ c.title }}</h3>
            <!--
        <span class="info due"><i class="fa fa-clock-o" aria-hidden="true"></i> <span>Today</span></span>
        <span class="info tasks"><i class="fa fa-list" aria-hidden="true"></i> <span>3/12</span></span>
        <span class="info members"><i class="fa fa-users" aria-hidden="true"></i> <span>4</span></span>
         //-->

        <button class="icon-more"></button>

        </a>
      </li>
      </ul>
        <!-- CREATE CARD //-->
      <div class="card create" style="background-color:#{{ boardservice.getCurrent().color }};">
          <form ng-submit="createCard(s.id, newCard.title)">
            <h3 ng-if="s.status.addCard" ><input type="text" autofocus-on-insert ng-model="newCard.title" ng-blur="s.status.addCard=false"/></h3>
          </form>
        <div class="fa fa-plus" ng-if="!s.status.addCard" ng-click="s.status.addCard=!s.status.addCard"></div>
      </div>
    </div>
    <div class="stack" style="display: inline-block;">
        <form class="ng-pristine ng-valid" ng-submit="createStack()">
        <h2>
            <input type="text" placeholder="Add a new stack" ng-focus="status.addStack=true" ng-blur="status.addStack=false" ng-model="newStack.title" >
            <button class="icon icon-add" ng-show="status.addStack" type="submit"></button>
        </h2>
            </form>
      </div>
    </div>

    </div>

      </div>

