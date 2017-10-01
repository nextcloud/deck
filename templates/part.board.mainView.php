<div id="board-status" ng-if="statusservice.active">
	<div id="emptycontent">
		<div class="icon-{{ statusservice.icon }}"></div>
		<h2>{{ statusservice.title }}</h2>
		<p>{{ statusservice.text }}</p></div>
</div>

<div id="controls">
	<div class="crumb svg last">
		<a href="#" class="icon-home" title="<?php p($l->t('All Boards')); ?>">
		</a>
	</div>
	<h2 class="title" ng-style="{'border-bottom':'2px solid #{{boardservice.getCurrent().color }}'}">
		{{ boardservice.getCurrent().title }}
	</h2>
	<div class="board-header-controls hidden">
		<?php print_unescaped($this->inc('part.board.headerControls')); ?>
	</div>
	<div class="board-header-controls app-popover-menu-utils">
		<button class="icon-more button"></button>
		<div class="popovermenu hidden">
			<div id="popover-controls">
				<?php print_unescaped($this->inc('part.board.headerControls')); ?>
			</div>
		</div>
	</div>
</div>

<div id="board" class="scroll-container" ng-click="sidebar.show=false" ui-sref="board">

	<search on-search="search" class="ng-hide"></search>

	<div id="innerBoard" data-ng-model="stacks" data-as-sortable="sortOptionsStack">
		<div class="stack" ng-repeat="s in stacks" data-as-sortable-item
			 data-columnindex="{{$index}}" id="column{{$index}}"
			 style="">
			<h3 data-as-sortable-item-handle>
				<span class="editable-inline" ng-show="!s.status.editStack" ng-click="s.status.editStack=true">{{ s.title }}</span>
				<form ng-if="s.status.editStack" ng-submit="stackservice.update(s); s.status.editStack=false">
					<input type="text" placeholder="<?php p($l->t('Add a new stack')); ?>"
						   ng-blur="stackservice.update(s); s.status.editStack=false" ng-model="s.title"
						   autofocus-on-insert required maxlength="100" />
				</form>
				<button class="icon-delete button-inline stack-actions"
						ng-if="!s.status.editStack"
                        ng-click="stackservice.delete(s.id)"></button>
			</h3>
			<ul data-as-sortable="sortOptions" is-disabled="!boardservice.canEdit() || filter==='archive'" data-ng-model="s.cards" class="card-list">
				<li class="card as-sortable-item"
					ng-repeat="c in s.cards"
					data-as-sortable-item
					ng-click="$event.stopPropagation()"
					ui-sref="board.card({boardId: id, cardId: c.id})"
					ng-class="{'archived': c.archived, 'has-labels': c.labels.length>0 }">
					<div data-as-sortable-item-handle>
						<div class="card-upper">
							<h4>{{ c.title }}</h4>
							<ul class="labels">
								<li ng-repeat="label in c.labels"
									ng-style="{'background-color':'#{{ label.color }}'}" title="{{ label.title }}">
									<span>{{ label.title }}</span>
								</li>
							</ul>

						</div>

						<div class="card-controls">
							<i class="icon icon-filetype-text" ng-if="c.description" title="{{ c.description }}"></i>
							<span class="due" ng-if="c.duedate" ng-class="{'overdue': c.overdue == 3, 'now': c.overdue == 2, 'next': c.overdue == 1  }" title="{{ c.duedate }}">
								<i class="icon icon-badge"></i>
								<span data-timestamp="{{ c.duedate | dateToTimestamp }}" class="live-relative-timestamp">{{ c.duedate | relativeDateFilterString }}</span>
							</span>
							<div class="card-assigned-users">
								<div class="assigned-user" ng-repeat="user in c.assignedUsers | limitTo: 3">
									<div class="avatardiv" avatar ng-attr-displayname="{{ user.participant.uid }}"></div>
								</div>
							</div>
							<div class="app-popover-menu-utils" ng-if="!boardservice.isArchived()">
								<button class="button-inline card-options icon-more" ng-model="card"></button>
								<div class="popovermenu hidden">
									<ul>
										<li ng-if="filter!=='archive'">
											<a class="menuitem action action-rename permanent"
											   data-action="Archive"
											   ng-click="cardArchive(c); $event.stopPropagation();"><span
														class="icon icon-archive"></span><span><?php p($l->t('Archive')); ?></span></a>
										</li>
										<li ng-if="filter==='archive'">
											<a class="menuitem action action-rename permanent"
											   data-action="Unarchive"
											   ng-click="cardUnarchive(c); $event.stopPropagation();"><span
														class="icon icon-archive"></span><span><?php p($l->t('Unarchive')); ?></span></a>
										</li>
										<li>
											<a class="menuitem action action-delete permanent"
											   data-action="Delete"
											   ng-click="cardDelete(c)"><span
														class="icon icon-delete"></span><span><?php p($l->t('Delete')); ?></span></a>
										</li>
									</ul>
								</div>
							</div>
						</div>

					</div>
				</li>
			</ul>

			<!-- CREATE CARD //-->
			<div class="card create"
				 ng-style="{'background-color':'#{{ boardservice.getCurrent().color }}'}" ng-if="boardservice.canEdit() && checkCanEdit() && filter!=='archive'">
				<form ng-submit="createCard(s.id, newCard.title)">
					<h4 ng-if="status.addCard[s.id]">
						<input type="text" autofocus-on-insert
							   ng-model="newCard.title"
							   ng-blur="status.addCard[s.id]=false"
							   ng-style="{'color':'{{ boardservice.getCurrent().color | textColorFilter }}','border-color':'{{ boardservice.getCurrent().color | textColorFilter }}'}"
							   maxlength="100"
							   required placeholder="<?php p($l->t('Enter a card title')); ?>"/>
					</h4>
				</form>
				<div ng-if="!status.addCard[s.id]" ng-click="status.addCard[s.id]=true">
					<i class="icon icon-add{{ boardservice.getCurrent().color | iconWhiteFilter }}"></i>
				</div>
			</div>
		</div>

	</div>

</div>
