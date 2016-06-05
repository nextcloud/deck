<div id="board-status" ng-if="status.active">
    <div id="emptycontent">
        <div class="icon-{{ status.icon }}"></div>
        <h2>{{ status.title }}</h2>
        <p>{{ status.text }}</p></div>
</div>
<div id="board" class="scroll-container" style="background-color:{{rgblight(board.color)}};">
    <h1 style="background-color:#{{ board.color }};" ng-click="stackservice.getOne(6)">{{ board.title }} {{ stackservice.once }}
    </h1>
    <?php /* maybe later
    <div class="board-actions">
      <button class="fa fa-share-alt"></button>
      <button class="fa fa-users"></button>
      <button class="fa fa-ellipsis-h"></button>
    </div> */ ?>

    <div id="innerBoard" data-ng-model="stacks">
    <div class="stack" ng-repeat="s in stacks" data-columnindex="{{$index}}" id="column{{$index}}" data-ng-model="stacks">
      <h2>{{ s.title }}
          <div class="stack-actions">
             <button class="icon-rename"></button>
              <button class="icon-delete"></button>
          </div>
      </h2>
        <ul data-as-sortable="sortOptions" data-ng-model="s.cards"  style="min-height: 40px;">
      <li class="card as-sortable-item" ng-repeat="c in s.cards" data-as-sortable-item>
        <a href="#/board/{{ id }}/card/{{ c.id }}" data-as-sortable-item-handle>
        <h3><i class="fa fa-github"></i> {{ c.title }}</h3>
        <span class="info due"><i class="fa fa-clock-o" aria-hidden="true"></i> <span>Today</span></span>
        <span class="info tasks"><i class="fa fa-list" aria-hidden="true"></i> <span>3/12</span></span>
        <span class="info members"><i class="fa fa-users" aria-hidden="true"></i> <span>4</span></span>

        <button class="icon-more"></button>

        <ul class="labels">
          <li style="color:#a00; border-color:#aa0000;">important</li>
          <li style="color:#0a0; border-color:#00aa00;">action-needed</li>
          <li style="color:#00a; border-color:#00a;">action-needed</li>
          <li style="color:#ac8ac8; border-color:#ac8ac8;">action-needed</li>
        </ul>
        </a>
      </li>
      </ul>
      <div class="card create">
          <h3><input type="text" /></h3>
        <i class="fa fa-plus"></i>
      </div>
    </div>
    <div class="stack" style="display: inline-block;">
        <form class="ng-pristine ng-valid" ng-submit="createStack()">
        <h2>
            <input type="text" placeholder="Add a new stack" ng-focus="status.addStack=true" ng-blur="status.addStack=false" ng-model="newStack.title">
            <button class="icon icon-add" ng-show="status.addStack" type="submit"></button>
        </h2>
            </form>
      </div>
    </div>

    </div>

      </div>

