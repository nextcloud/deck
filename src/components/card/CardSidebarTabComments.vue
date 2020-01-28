<template>
	<div>
		<div class="comment--header">
			<Avatar :user="card.owner.uid" />
			<span class="has-tooltip username">
				{{ card.owner.displayname }}
			</span>
		</div>

		<div class="comment-form">
			<form @submit.prevent="createComment()">
				<EditorContent :editor="editor"
					:placeholder="t('deck', 'New comment') + ' ...'"
					class="editor__content"
					required />
				<input v-tooltip="t('deck', 'Save')"
					class="icon-confirm"
					type="submit"
					value="">
			</form>
		</div>

		<div v-show="showSuggestions" ref="suggestions" class="suggestion-list">
			<template v-if="hasResults">
				<div
					v-for="(user, index) in filteredUsers"
					:key="user.uid"
					:class="{ 'is-selected': navigatedUserIndex === index }"
					class="suggestion-list__item"
					@click="selectUser(user)">
					{{ user.displayname }}
				</div>
			</template>
			<div v-else class="suggestion-list__item is-empty">
				{{ t('deck', 'No users found') }}
			</div>
		</div>

		<ul v-if="comments[card.id] && comments[card.id].length > 0" id="commentsFeed">
			<CommentItem v-for="comment in comments[card.id]"
				:key="comment.id"
				:comment="comment"
				@doReload="loadComments" />
			<a @click="loadMore">
				{{ t('deck', 'Load More') }}
			</a>
		</ul>
		<div v-else-if="isLoading" class="icon icon-loading" />
		<div v-else class="emptycontent">
			<div class="icon-comment" />
			<p>{{ t('deck', 'No comments yet. Begin the discussion!') }}</p>
		</div>
	</div>
</template>

<script>
import Fuse from 'fuse.js'
import tippy from 'tippy.js'
import { Editor, EditorContent } from 'tiptap'
import { Mention } from 'tiptap-extensions'

import { mapState } from 'vuex'
import { Avatar } from '@nextcloud/vue'
import CommentItem from './CommentItem'

export default {
	name: 'CardSidebarTabComments',
	components: {
		Avatar,
		CommentItem,
		EditorContent,
	},
	props: {
		card: {
			type: Object,
			default: undefined,
		},
	},
	data() {
		return {
			newComment: '',
			isLoading: false,
			limit: 20,
			offset: 0,

			editor: new Editor({
				extensions: [
					new Mention({
						// a list of all suggested items
						items: () => {
							return this.currentBoard.users
						},
						// is called when a suggestion starts
						onEnter: ({
							items, query, range, command, virtualNode,
						}) => {
							this.query = query
							this.filteredUsers = items
							this.suggestionRange = range
							this.renderPopup(virtualNode)
							// we save the command for inserting a selected mention
							// this allows us to call it inside of our custom popup
							// via keyboard navigation and on click
							this.insertMention = command
						},
						// is called when a suggestion has changed
						onChange: ({
							items, query, range, virtualNode,
						}) => {
							this.query = query
							this.filteredUsers = items
							this.suggestionRange = range
							this.navigatedUserIndex = 0
							this.renderPopup(virtualNode)
						},
						// is called when a suggestion is cancelled
						onExit: () => {
							// reset all saved values
							this.query = null
							this.filteredUsers = []
							this.suggestionRange = null
							this.navigatedUserIndex = 0
							this.destroyPopup()
						},
						// is called on every keyDown event while a suggestion is active
						onKeyDown: ({ event }) => {
							// pressing up arrow
							if (event.keyCode === 38) {
								this.upHandler()
								return true
							}
							// pressing down arrow
							if (event.keyCode === 40) {
								this.downHandler()
								return true
							}
							// pressing enter
							if (event.keyCode === 13) {
								this.enterHandler()
								return true
							}
							return false
						},
						// is called when a suggestion has changed
						// this function is optional because there is basic filtering built-in
						// you can overwrite it if you prefer your own filtering
						// in this example we use fuse.js with support for fuzzy search
						onFilter: (items, query) => {
							if (!query) {
								return items
							}
							const fuse = new Fuse(items, {
								threshold: 0.2,
								keys: ['uid', 'displayname'],
							})
							return fuse.search(query)
						},
					}),
				],
				content: '',
				onUpdate: ({ getHTML }) => {
					this.newComment = getHTML().replace(/(<p>|<\/p>)/g, '')
				},
			}),
			query: null,
			suggestionRange: null,
			filteredUsers: [],
			navigatedUserIndex: 0,
			insertMention: () => {},
			observer: null,

		}
	},
	computed: {
		...mapState({
			comments: state => state.comment.comments,
			currentBoard: state => state.currentBoard,
		}),

		hasResults() {
			return this.filteredUsers.length
		},
		showSuggestions() {
			return this.query || this.hasResults
		},

	},
	watch: {
		'card': {
			immediate: true,
			handler() {
				this.loadComments()
			},
		},
	},
	created() {
	},

	methods: {
		loadComments() {
			this.isLoading = true
			this.card.limit = this.limit
			this.card.offset = this.offset
			this.$store.dispatch('listComments', this.card).then(response => {
				this.isLoading = false
			})
		},
		createComment() {
			const commentObj = {
				cardId: this.card.id,
				comment: this.newComment,
			}
			this.$store.dispatch('createComment', commentObj)
			this.loadComments()
			this.newComment = ''
			this.editor.setContent('')
		},
		loadMore() {
			this.offset = this.offset + this.limit
			this.loadComments()
		},

		// navigate to the previous item
		// if it's the first item, navigate to the last one
		upHandler() {
			this.navigatedUserIndex = ((this.navigatedUserIndex + this.filteredUsers.length) - 1) % this.filteredUsers.length
		},
		// navigate to the next item
		// if it's the last item, navigate to the first one
		downHandler() {
			this.navigatedUserIndex = (this.navigatedUserIndex + 1) % this.filteredUsers.length
		},
		enterHandler() {
			const user = this.filteredUsers[this.navigatedUserIndex]
			if (user) {
				this.selectUser(user)
			}
		},
		// we have to replace our suggestion text with a mention
		// so it's important to pass also the position of your suggestion text
		selectUser(user) {
			this.insertMention({
				range: this.suggestionRange,
				attrs: {
					id: user.uid,
					label: user.displayname,
				},
			})
			this.editor.focus()
		},
		// renders a popup with suggestions
		// tiptap provides a virtualNode object for using popper.js (or tippy.js) for popups
		renderPopup(node) {
			if (this.popup) {
				return
			}
			this.popup = tippy(node, {
				content: this.$refs.suggestions,
				trigger: 'mouseenter',
				interactive: true,
				placement: 'bottom-start',
				inertia: true,
				duration: [400, 200],
				showOnInit: true,
			})
			// we have to update tippy whenever the DOM is updated
			if (MutationObserver) {
				this.observer = new MutationObserver(() => {
					this.popup.popperInstance.scheduleUpdate()
				})
				this.observer.observe(this.$refs.suggestions, {
					childList: true,
					subtree: true,
					characterData: true,
				})
			}
		},
		destroyPopup() {
			if (this.popup) {
				this.popup.destroy()
				this.popup = null
			}
			if (this.observer) {
				this.observer.disconnect()
			}
		},
	},
}
</script>

<style scoped lang="scss">
	@import "../../css/comments";
</style>
