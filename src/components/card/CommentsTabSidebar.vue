<template>
	<div>
		<div id="userDiv">
			<avatar :user="card.owner.uid" />
			<span class="has-tooltip username">
				{{ card.owner.displayname }}
			</span>
		</div>

		<!-- <div id="commentForm">
			<form @submit.prevent="createComment()">
				<input :placeholder="t('deck', 'New comment') + ' ...'" v-model="newComment" type="text"
					autofocus required>
				<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit"
					value="">
			</form>
		</div>
 -->

		<div class="editor" id="commentForm">
			<form @submit.prevent="createComment()">
				<editor-content :editor="editor" class="editor__content" 
				:placeholder="t('deck', 'New comment') + ' ...'" 
				required />
				<input v-tooltip="t('deck', 'Save')" class="icon-confirm" type="submit" value="">
			</form>
		</div>

		<div v-show="showSuggestions" ref="suggestions" class="suggestion-list">
			<template v-if="hasResults">
				<div
					v-for="(user, index) in filteredUsers"
					:key="user.id"
					:class="{ 'is-selected': navigatedUserIndex === index }"
					class="suggestion-list__item"
					@click="selectUser(user)"
				>
					{{ user.name }}
				</div>
			</template>
			<div v-else class="suggestion-list__item is-empty">
				No users found
			</div>
		</div>



		<div v-if="isLoading" class="icon icon-loading" />

		<ul id="commentsFeed">
			<CommentItem v-for="comment in comments[card.id]" :comment="comment" :key="comment.id"
				@doReload="loadComments" />
		</ul>
		<button @click="loadMore">Load More</button>

	</div>
</template>

<script>
import Fuse from 'fuse.js'
import tippy from 'tippy.js'
import { Editor, EditorContent, EditorMenuBar } from 'tiptap'
import {
	HardBreak,
	Heading,
	Mention,
	Code,
	Bold,
	Italic
} from 'tiptap-extensions'

import { mapState } from 'vuex'
import { Avatar } from 'nextcloud-vue'
import { Actions } from 'nextcloud-vue/dist/Components/Actions'
import { ActionButton } from 'nextcloud-vue/dist/Components/ActionButton'
import CommentItem from './CommentItem'

export default {
	name: 'CommentsTabSidebar',
	components: {
		Avatar,
		Actions,
		ActionButton,
		CommentItem,
    	EditorContent,
    	EditorMenuBar
	},
	props: {
		card: {
			type: Object,
			default: undefined
		}
	},
	data() {
		return {
			newComment: '',
			isLoading: false,
			limit: 20,
			offset: 0,

			editor: new Editor({
				extensions: [
					new HardBreak(),
					new Heading({ levels: [1, 2, 3] }),
					new Mention({
						// a list of all suggested items
						items: () => [
							{ id: 1, name: 'Philipp Kühn' },
							{ id: 2, name: 'Hans Pagel' },
							{ id: 3, name: 'Kris Siepert' },
							{ id: 4, name: 'Justin Schueler' }
						],
						// items: this.currentBoard.users,
						// is called when a suggestion starts
						onEnter: ({
							items, query, range, command, virtualNode
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
							items, query, range, virtualNode
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
								keys: ['name']
							})
							return fuse.search(query)
						}
					}),
					new Code(),
					new Bold(),
					new Italic()
				],
				content: '',
				onUpdate: ({getHTML}) => {
					this.newComment = getHTML().replace(/(<p>|<\/p>)/g, "")
				}, 
			}),
			query: null,
			suggestionRange: null,
			filteredUsers: [],
			navigatedUserIndex: 0,
			insertMention: () => {},
			observer: null

		}
	},
	computed: {
		...mapState({
			comments: state => state.comment.comments,
			currentBoard: state => state.currentBoard
		}),

		hasResults() {
			return this.filteredUsers.length
		},
		showSuggestions() {
			return this.query || this.hasResults
		}

	},
	watch: {
		'card': {
			immediate: true,
			handler() {
				this.loadComments()
			}
		}
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
			let commentObj = {
				cardId: this.card.id,
				comment: this.newComment
			}
			this.$store.dispatch('createComment', commentObj)
			this.loadComments()
			this.newComment = ''
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
					label: user.displayname
				}
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
				theme: 'dark',
				placement: 'top-start',
				inertia: true,
				duration: [400, 200],
				showOnInit: true,
				arrow: true,
				arrowType: 'round'
			})
			// we have to update tippy whenever the DOM is updated
			if (MutationObserver) {
				this.observer = new MutationObserver(() => {
					this.popup.popperInstance.scheduleUpdate()
				})
				this.observer.observe(this.$refs.suggestions, {
					childList: true,
					subtree: true,
					characterData: true
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
		}
	}
}
</script>

<style lang="scss">
	#commentForm form {
		display: flex
	}
	#commentForm form {
		flex-grow: 1;
	}
	.editor__content {
		flex-grow: 1;
	}
	#userDiv {
		margin-bottom: 20px;
	}
	.username {
		padding: 12px 9px;
		flex-grow: 1;
	}
</style>
