/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { constants, dropHandlers, smoothDnD } from 'smooth-dnd'
import { defineComponent, h, onBeforeUnmount, onMounted, onUpdated, ref } from 'vue'

const transientBodyClasses = [
	'smooth-dnd-no-user-select',
	'smooth-dnd-disable-touch-action',
]

smoothDnD.dropHandler = dropHandlers.reactDropHandler().handler
smoothDnD.wrapChild = false

function createContainerOptions(props, emit) {
	return {
		behaviour: props.behaviour,
		groupName: props.groupName,
		orientation: props.orientation,
		dragHandleSelector: props.dragHandleSelector,
		nonDragAreaSelector: props.nonDragAreaSelector,
		dragBeginDelay: props.dragBeginDelay,
		animationDuration: props.animationDuration,
		autoScrollEnabled: props.autoScrollEnabled,
		lockAxis: props.lockAxis,
		dragClass: props.dragClass,
		dropClass: props.dropClass,
		removeOnDropOut: props.removeOnDropOut,
		getChildPayload: props.getChildPayload,
		shouldAnimateDrop: props.shouldAnimateDrop,
		shouldAcceptDrop: props.shouldAcceptDrop,
		getGhostParent: props.getGhostParent,
		dropPlaceholder: props.dropPlaceholder,
		onDragStart: (params) => emit('drag-start', params),
		onDragEnd: (params) => emit('drag-end', params),
		onDrop: (params) => emit('drop', params),
		onDragEnter: (params) => emit('drag-enter', params),
		onDragLeave: (params) => emit('drag-leave', params),
		onDropReady: (params) => emit('drop-ready', params),
	}
}

const tagProp = {
	type: [String, Object],
	default: 'div',
}

function getTagDefinition(tag, extraClass) {
	if (typeof tag === 'object' && tag !== null) {
		return {
			value: tag.value || 'div',
			props: {
				...(tag.props || {}),
				class: [tag.props?.class, extraClass],
			},
		}
	}

	return {
		value: tag || 'div',
		props: extraClass ? { class: extraClass } : {},
	}
}

export const DeckDragContainer = defineComponent({
	name: 'DeckDragContainer',
	inheritAttrs: false,
	emits: ['drag-start', 'drag-end', 'drop', 'drag-enter', 'drag-leave', 'drop-ready'],
	props: {
		behaviour: String,
		groupName: String,
		orientation: {
			type: String,
			default: 'vertical',
		},
		dragHandleSelector: String,
		nonDragAreaSelector: String,
		dragBeginDelay: Number,
		animationDuration: Number,
		autoScrollEnabled: {
			type: Boolean,
			default: true,
		},
		lockAxis: String,
		dragClass: String,
		dropClass: String,
		removeOnDropOut: {
			type: Boolean,
			default: false,
		},
		getChildPayload: Function,
		shouldAnimateDrop: Function,
		shouldAcceptDrop: Function,
		getGhostParent: Function,
		dropPlaceholder: [Object, Boolean],
		tag: tagProp,
	},
	setup(props, { attrs, emit, slots }) {
		const containerElement = ref(null)
		let container = null

		const initializeContainer = () => {
			if (!containerElement.value) {
				return
			}

			container = smoothDnD(containerElement.value, createContainerOptions(props, emit))
		}

		onMounted(() => {
			initializeContainer()
		})

		onUpdated(() => {
			if (!containerElement.value) {
				return
			}

			if (!container) {
				initializeContainer()
				return
			}

			container.setOptions(createContainerOptions(props, emit))
		})

		onBeforeUnmount(() => {
			container?.dispose()
			container = null
		})

		return () => {
			const tagDefinition = getTagDefinition(props.tag)
			return h(tagDefinition.value, {
				ref: containerElement,
				...tagDefinition.props,
				...attrs,
				class: [attrs.class, tagDefinition.props.class, 'smooth-dnd-container', props.orientation],
			}, slots.default ? slots.default() : [])
		}
	},
})

export const DeckDraggable = defineComponent({
	name: 'DeckDraggable',
	inheritAttrs: false,
	props: {
		tag: tagProp,
	},
	setup(props, { attrs, slots }) {
		return () => {
			const tagDefinition = getTagDefinition(props.tag, constants.wrapperClass)
			return h(tagDefinition.value, {
				...tagDefinition.props,
				...attrs,
				class: [attrs.class, tagDefinition.props.class, constants.wrapperClass],
			}, slots.default ? slots.default() : [])
		}
	},
})

export function resetDeckDndDocumentState(target = document.body) {
	if (!target?.classList) {
		return
	}

	transientBodyClasses.forEach((className) => {
		target.classList.remove(className)
	})
}
