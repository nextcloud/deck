<div id="card-header">

<h2>{{ cardservice.getCurrent().title }}<a class="icon-close" ng-click="sidebar.show=!sidebar.show"> &nbsp;</a></h2>
    Modified: {{ cardservice.getCurrent().modifiedAt }}
    Created: {{ cardservice.getCurrent().createdAt }}
    <ul class="labels">
        <li style="color:#a00; border-color:#aa0000;">important</li>
        <li style="color:#0a0; border-color:#00aa00;">action-needed</li>
        <li style="color:#00a; border-color:#00a;">action-needed</li>
        <li style="color:#ac8ac8; border-color:#ac8ac8;">action-needed</li>
    </ul>
    <div id="assigned-users">
        <div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(213, 231, 116);">D</div>
        <div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(213, 120, 220);">E</div>
        <div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(120, 120, 220);">C</div>
        <div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(120, 220, 220);">K</div>
        <div class="avatardiv" style="height: 30px; width: 30px; color: rgb(255, 255, 255); font-weight: normal; text-align: center; line-height: 30px; font-size: 17px; background-color: rgb(220, 220, 220);">+</div>
    </div>

<div id="card-description">
    <textarea>
    {{ card.description }}
        </textarea>
</div>

</div>

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
</div>