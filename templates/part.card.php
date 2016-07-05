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
            <!-- TODO: change to textarea elastic //-->
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

        <ui-select multiple tagging="" ng-model="card.labels" theme="bootstrap" style="width:100%;" title="Choose a label" placeholder="Add a label"
                   on-select="labelAssign($item, $model)" on-remove="labelRemove($item, $model)">
            <ui-select-match placeholder="Select labels..."><span class="select-label" style="background-color:#{{$item.color}}">{{$item.title}}</span></ui-select-match>
            <ui-select-choices repeat="label in boardservice.getCurrent().labels | filter:$select.search">
                <span style="background-color:#{{label.color}}">{{label.title}}</span>
            </ui-select-choices>
        </ui-select>

    <div id="assigned-users">
        <ui-select multiple tagging="" ng-model="card.assignees" theme="bootstrap" style="width:100%;" title="Choose a user to assign" placeholder="Assign users ..."
                   on-select="userAssign($item, $model)" on-remove="userRemove($item, $model)">
            <ui-select-match placeholder="Select users...">{{$item.title}}</ui-select-match>
            <ui-select-choices repeat="label in boardservice.getCurrent().labels | filter:$select.search">
                <span style="background-color:#{{label.color}}">{{label.title}}</span>
            </ui-select-choices>
        </ui-select>
    </div>

<div id="card-description">
    <h3>Description</h3>
    <textarea elastic ng-if="status.description" placeholder="Enter your description here ..." ng-blur="updateCard(cardservice.getCurrent())" ng-model="cardservice.getCurrent().description" autofocus-on-insert> </textarea>
    <div class="container" ng-click="editDescription()" ng-show="!status.description" ng-animate><div ng-bind-html="cardservice.getCurrent().description | markdown"></div><div class="placeholder" ng-if="!cardservice.getCurrent().description">Add a card description ...</div></div>

</div>


</div>


<ul class="tabHeaders">
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==0 || !status.boardtab)}" ng-click="status.boardtab=0"><a>Attachments</a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==1)}" ng-click="status.boardtab=1"><a>Comments</a></li>
    <li class="tabHeader" ng-class="{'selected': (status.boardtab==2)}" ng-click="status.boardtab=2"><a>History</a></li>
</ul>
<div class="tabsContainer">
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==0 || !status.boardtab">

        <div id="card-attachments">
            <button ng-click="status.addAttachment=true"><i class="fa fa-plus"></i> Add an attachment</button>
            <div ng-if="status.addAttachment" id="attachment-add">
            <button><i class="fa fa-file"></i> Attach a File</button>
            <button><i class="fa fa-link"></i> Attach a URL</button>
            <button><i class="fa fa-calendar"></i> Attach an Event</button>
            <button><i class="fa fa-user"></i> Attach an Contact</button>
            <button><i class="fa fa-image"></i> Attach an Image</button>
            </div>
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



    </div>
    <div id="board-detail-labels" class="tab commentsTabView" ng-if="status.boardtab==1">



    </div>
    <div id="commentsTabView" class="tab commentsTabView" ng-if="status.boardtab==2">

    </div>
</div>


<!--

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