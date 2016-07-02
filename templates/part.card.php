<div id="board-status" ng-if="statusservice.active">
    <div id="emptycontent">
        <div class="icon-{{ statusservice.icon }}"></div>
        <h2>{{ statusservice.title }}</h2>
        <p>{{ statusservice.text }}</p></div>
</div>
{{card=cardservice.getCurrent();""}}
<div id="card-header">
    <a class="icon-close" ui-sref="board" ng-click="sidebar.show=!sidebar.show"> &nbsp;</a>
    <h2>
        <form ng-submit="renameCard(cardservice.getCurrent())">
            <input class="input-inline" type="text" ng-if="status.renameCard" ng-model="cardservice.getCurrent().title" ng-blur="renameCard(cardservice.getCurrent())" autofocus-on-insert required>
        </form>
        <div ng-click="status.renameCard=true" ng-show="!status.renameCard">{{ cardservice.getCurrent().title }}</div>
    </h2>
</div>

    <div id="card-meta" class="card-block">
        <div id="card-dates">
            Modified: <span>{{ cardservice.getCurrent().lastModified*1000|date:'medium' }}</span>
            Created: <span>{{ cardservice.getCurrent().createdAt*1000|date:'medium' }}</span>
            by <span>{{ cardservice.getCurrent().owner }}</span>
        </div>

        <ui-select multiple tagging tagging-label="(custom 'new' label)" ng-model="card.labels" theme="bootstrap" style="width:100%;" title="Choose a label" placeholder="Add a label"
                   on-select="labelAssign($item, $model)" on-remove="labelRemove($item, $model)">
            <ui-select-match placeholder="Select labels..."><span class="select-label" style="background-color:#{{$item.color}}">{{$item.title}}</span></ui-select-match>
            <ui-select-choices repeat="label in boardservice.getCurrent().labels | filter:$select.search">
                <span style="background-color:#{{label.color}}">{{label.title}}</span>
            </ui-select-choices>
        </ui-select>

        <br style="clear:both;"/>
        <br style="clear:both;"/>

    <div id="assigned-users">
        <ui-select multiple tagging tagging-label="(custom 'new' label)" ng-model="card.assignees" theme="bootstrap" style="width:100%;" title="Choose a label">
            <ui-select-match placeholder="Select labels..."><span style="background-color:#{{$item.color}}">{{$item.title}}</span></ui-select-match>
            <ui-select-choices repeat="label in boardservice.getCurrent().labels | filter:$select.search">
                <div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(213, 231, 116);">D</div>
            </ui-select-choices>
        </ui-select>
    </div>

<div id="card-description">
    <h3>Description</h3>
    <textarea ng-if="status.description" placeholder="Enter your description here ..." ng-blur="updateCard(cardservice.getCurrent())" ng-model="cardservice.getCurrent().description" autofocus-on-insert> </textarea>
    <div class="container" ng-click="editDescription()" ng-show="!status.description" ng-animate><div ng-bind-html="cardservice.getCurrent().description | markdown"></div><div class="placeholder" ng-if="!cardservice.getCurrent().description">Add a card description ...</div></div>

</div>


</div>
<ul class="tabHeaders">				<li class="tabHeader selected" data-tabid="commentsTabView" data-tabindex="0">			<a href="#">Kommentare</a>		</li>				<li class="tabHeader" data-tabid="shareTabView" data-tabindex="1">			<a href="#">Anhänge</a>		</li>				<li class="tabHeader" data-tabid="versionsTabView" data-tabindex="2">			<a href="#">Beschreibung</a>		</li>			</ul>

<!--
<div id="card-attachments">
    <h3>Attachments</h3>
    <ul>
        <li>
            <a href="#">
                <span class="fa fa-file"></span> clienta_webdesign_darft_032.pdf
                <div class="details">
                <span class="user">Added by John Doe at</span>
                <span class="added">1.3.2014 14:13</span>
                </div>
            </a>
        </li>
    </ul>
</div>
<div class="card-block">

<h3>Comments</h3>
</div>
<div class="card-block">
<h3>Build Status</h3>
    <p>
        Autem inventore et exercitationem quas voluptatem perspiciatis nostrum. Eligendi numquam officia quas facere voluptas mollitia. Blanditiis quia eveniet ipsum magnam. Et consectetur repellat eum odio impedit dolorem veritatis. Aperiam delectus qui quis enim consequatur nihil. Provident molestiae et occaecati facere.
        Quod perspiciatis ea dolores nostrum numquam rerum consectetur ut. Ex voluptatem fugiat officia voluptas et officia eaque consequatur. Voluptas minus soluta minima consequatur aspernatur ad voluptas. Neque et deleniti sunt a reprehenderit rerum.
        Non rerum natus recusandae dolorem nihil. Impedit dolore molestiae dolorum aspernatur. Impedit nulla dolore amet consectetur voluptatem iusto sit. Repellendus in pariatur officiis eos necessitatibus saepe est ut. Quia vel adipisci voluptate expedita hic. Ad sed quia aut inventore consequatur.
        Quia quia qui aspernatur cumque quo omnis corporis. Reprehenderit id sint architecto magni in. Et harum sequi eaque quasi qui sed id quod.
        Officia quaerat facere et totam officiis dolores velit qui. Earum velit sint quia. Id libero quibusdam voluptatem.
    </p>
    <p>
    Autem inventore et exercitationem quas voluptatem perspiciatis nostrum. Eligendi numquam officia quas facere voluptas mollitia. Blanditiis quia eveniet ipsum magnam. Et consectetur repellat eum odio impedit dolorem veritatis. Aperiam delectus qui quis enim consequatur nihil. Provident molestiae et occaecati facere.
    Quod perspiciatis ea dolores nostrum numquam rerum consectetur ut. Ex voluptatem fugiat officia voluptas et officia eaque consequatur. Voluptas minus soluta minima consequatur aspernatur ad voluptas. Neque et deleniti sunt a reprehenderit rerum.
    Non rerum natus recusandae dolorem nihil. Impedit dolore molestiae dolorum aspernatur. Impedit nulla dolore amet consectetur voluptatem iusto sit. Repellendus in pariatur officiis eos necessitatibus saepe est ut. Quia vel adipisci voluptate expedita hic. Ad sed quia aut inventore consequatur.
    Quia quia qui aspernatur cumque quo omnis corporis. Reprehenderit id sint architecto magni in. Et harum sequi eaque quasi qui sed id quod.
    Officia quaerat facere et totam officiis dolores velit qui. Earum velit sint quia. Id libero quibusdam voluptatem.
</p>
</div>// -->